/**
 * Purpose: Leaflet/OpenStreetMap admin GPS tracking map.
 * Shows current vehicle location, route path, distance, and risk status.
 */
(function () {
  "use strict";

  var mapElement = document.querySelector("[data-live-gps-map]");
  if (!(mapElement instanceof HTMLElement)) {
    return;
  }

  var vehicleList = document.querySelector("[data-gps-vehicle-list]");
  var selectedPanel = document.querySelector("[data-gps-selected]");
  var refreshButton = document.querySelector("[data-gps-refresh]");
  var simulateButton = document.querySelector("[data-gps-simulate-selected]");
  var feedStatus = document.querySelector("[data-gps-feed-status]");

  var feedUrl = mapElement.dataset.feedUrl || "ajax/gps-live-feed.php";
  var simulateUrl =
    mapElement.dataset.simulateUrl || "ajax/gps-simulate-location.php";

  if (typeof L === "undefined") {
    mapElement.innerHTML =
      '<div class="ridex-gps-map-error">Leaflet map library could not load. Please check your internet connection.</div>';
    return;
  }

  var kathmandu = [27.7172, 85.324];
  var map = L.map(mapElement, {
    scrollWheelZoom: true,
  }).setView(kathmandu, 12);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map);

  var markers = new Map();
  var selectedVehicleId = null;
  var selectedBookingId = null;
  var selectedRouteLine = null;
  var latestVehicles = [];

  var initialParams = new URLSearchParams(window.location.search);
  var initialVehicleId = Number.parseInt(
    initialParams.get("vehicle_id") || "0",
    10,
  );
  var initialBookingId = Number.parseInt(
    initialParams.get("booking_id") || "0",
    10,
  );

  var escapeHtml = function (value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };

  var buildMapsUrl = function (latitude, longitude) {
    if (typeof latitude !== "number" || typeof longitude !== "number") {
      return "";
    }

    return (
      "https://www.google.com/maps/search/?api=1&query=" +
      encodeURIComponent(latitude.toFixed(6) + "," + longitude.toFixed(6))
    );
  };

  var setStatus = function (message, mode) {
    if (!(feedStatus instanceof HTMLElement)) {
      return;
    }

    feedStatus.textContent = message;
    feedStatus.classList.remove("is-ok", "is-error", "is-loading");
    feedStatus.classList.add(mode || "is-ok");
  };

  var setKpi = function (name, value) {
    var node = document.querySelector('[data-gps-kpi="' + name + '"]');
    if (node instanceof HTMLElement) {
      node.textContent = value;
    }
  };

  var getRiskClass = function (vehicle) {
    var risk =
      vehicle && vehicle.risk ? String(vehicle.risk.level || "low") : "low";
    if (["low", "medium", "high"].indexOf(risk) === -1) {
      risk = "low";
    }
    return risk;
  };

  var makeMarkerIcon = function (vehicle) {
    var risk = getRiskClass(vehicle);
    return L.divIcon({
      className: "ridex-gps-leaflet-marker-wrapper",
      html:
        '<span class="ridex-gps-leaflet-marker ridex-gps-leaflet-marker--' +
        risk +
        '"><span class="material-symbols-rounded">directions_car</span></span>',
      iconSize: [42, 42],
      iconAnchor: [21, 21],
      popupAnchor: [0, -18],
    });
  };

  var markerPopupHtml = function (vehicle) {
    return (
      '<div class="ridex-gps-popup">' +
      "<strong>" +
      escapeHtml(vehicle.vehicleName || "Vehicle") +
      "</strong>" +
      "<span>Booking: " +
      escapeHtml(vehicle.bookingNumber || "N/A") +
      "</span>" +
      "<span>Customer: " +
      escapeHtml(vehicle.customerName || "Unknown") +
      "</span>" +
      "<span>Status: " +
      escapeHtml(vehicle.bookingStatus || "reserved") +
      "</span>" +
      "</div>"
    );
  };

  var updateKpis = function (kpis) {
    kpis = kpis || {};
    setKpi("activeTrips", String(kpis.activeTrips || 0));
    setKpi("gpsOnline", String(kpis.gpsOnline || 0));
    setKpi("gpsLost", String(kpis.gpsLost || 0));
    setKpi("highRisk", String(kpis.highRisk || 0));
  };

  var renderSelectedVehicle = function (vehicle) {
    if (!(selectedPanel instanceof HTMLElement)) {
      return;
    }

    if (!vehicle) {
      selectedPanel.innerHTML =
        '<p class="ridex-gps-empty">No vehicle selected yet.</p>';
      return;
    }

    selectedPanel.innerHTML =
      '<article class="ridex-gps-detail-card ridex-gps-detail-card--' +
      getRiskClass(vehicle) +
      '">' +
      '<div class="ridex-gps-detail-card__top">' +
      "<div><h3>" +
      escapeHtml(vehicle.vehicleName || "Vehicle") +
      "</h3><p>" +
      escapeHtml(vehicle.licensePlate || "License unavailable") +
      "</p></div>" +
      '<span class="ridex-gps-risk-badge ridex-gps-risk-badge--' +
      getRiskClass(vehicle) +
      '">' +
      escapeHtml(
        vehicle.risk && vehicle.risk.label ? vehicle.risk.label : "Low Risk",
      ) +
      "</span>" +
      "</div>" +
      '<dl class="ridex-gps-detail-list">' +
      "<div><dt>Booking</dt><dd>" +
      escapeHtml(vehicle.bookingNumber || "N/A") +
      "</dd></div>" +
      "<div><dt>Customer</dt><dd>" +
      escapeHtml(vehicle.customerName || "Unknown") +
      "</dd></div>" +
      "<div><dt>Phone</dt><dd>" +
      escapeHtml(vehicle.customerPhone || "Unavailable") +
      "</dd></div>" +
      "<div><dt>Pickup</dt><dd>" +
      escapeHtml(vehicle.pickupLocation || "Unavailable") +
      "</dd></div>" +
      "<div><dt>Return</dt><dd>" +
      escapeHtml(vehicle.returnLocation || "Unavailable") +
      "</dd></div>" +
      "<div><dt>Last GPS</dt><dd>" +
      escapeHtml(vehicle.lastUpdated || "Unavailable") +
      "</dd></div>" +
      "<div><dt>Route Distance</dt><dd>" +
      escapeHtml(vehicle.distanceKm || 0) +
      " km</dd></div>" +
      "<div><dt>Demo Route</dt><dd>" +
      escapeHtml(vehicle.demoRouteName || "Vehicle-specific route") +
      "</dd></div>" +
      "<div><dt>Risk Reason</dt><dd>" +
      escapeHtml(
        vehicle.risk && vehicle.risk.reason
          ? vehicle.risk.reason
          : "No risk rule triggered.",
      ) +
      "</dd></div>" +
      "</dl>" +
      "</article>";
  };

  var drawRoute = function (vehicle) {
    if (selectedRouteLine) {
      map.removeLayer(selectedRouteLine);
      selectedRouteLine = null;
    }

    if (!vehicle || !Array.isArray(vehicle.route) || vehicle.route.length < 2) {
      return;
    }

    var coordinates = vehicle.route
      .filter(function (point) {
        return typeof point.lat === "number" && typeof point.lng === "number";
      })
      .map(function (point) {
        return [point.lat, point.lng];
      });

    if (coordinates.length < 2) {
      return;
    }

    selectedRouteLine = L.polyline(coordinates, {
      weight: 5,
      opacity: 0.78,
    }).addTo(map);

    map.fitBounds(selectedRouteLine.getBounds(), {
      padding: [40, 40],
      maxZoom: 14,
    });
  };

  var selectVehicle = function (vehicleId, bookingId) {
    selectedVehicleId = Number(vehicleId);
    selectedBookingId = bookingId ? Number(bookingId) : null;

    var vehicle = null;
    if (
      selectedBookingId &&
      Number.isFinite(selectedBookingId) &&
      selectedBookingId > 0
    ) {
      vehicle =
        latestVehicles.find(function (item) {
          return Number(item.bookingId) === selectedBookingId;
        }) || null;
    }

    if (!vehicle) {
      vehicle =
        latestVehicles.find(function (item) {
          return Number(item.vehicleId) === selectedVehicleId;
        }) || null;
    }

    if (vehicle) {
      selectedVehicleId = Number(vehicle.vehicleId);
      selectedBookingId = Number(vehicle.bookingId) || selectedBookingId;
    }

    renderSelectedVehicle(vehicle || null);
    drawRoute(vehicle || null);

    if (
      vehicle &&
      vehicle.hasGpsSignal &&
      typeof vehicle.lat === "number" &&
      typeof vehicle.lng === "number"
    ) {
      map.setView([vehicle.lat, vehicle.lng], Math.max(map.getZoom(), 13));
      var marker = markers.get(Number(vehicle.vehicleId));
      if (marker) {
        marker.openPopup();
      }
    }

    if (vehicleList instanceof HTMLElement) {
      Array.from(vehicleList.querySelectorAll("[data-gps-vehicle-id]")).forEach(
        function (button) {
          var buttonVehicleId = Number(button.dataset.gpsVehicleId);
          var buttonBookingId = Number(button.dataset.gpsBookingId || "0");
          var isSelected = selectedBookingId
            ? buttonBookingId === selectedBookingId
            : buttonVehicleId === selectedVehicleId;
          button.classList.toggle("is-selected", isSelected);
        },
      );
    }
  };

  var renderVehicleList = function (vehicles) {
    if (!(vehicleList instanceof HTMLElement)) {
      return;
    }

    if (!Array.isArray(vehicles) || vehicles.length === 0) {
      vehicleList.innerHTML =
        '<p class="ridex-gps-empty">No active rented vehicles found.</p>';
      renderSelectedVehicle(null);
      return;
    }

    vehicleList.innerHTML = vehicles
      .map(function (vehicle) {
        return (
          '<button type="button" class="ridex-gps-vehicle-item" data-gps-vehicle-id="' +
          escapeHtml(vehicle.vehicleId) +
          '" data-gps-booking-id="' +
          escapeHtml(vehicle.bookingId || "0") +
          '">' +
          '<span class="ridex-gps-vehicle-item__name">' +
          escapeHtml(vehicle.vehicleName || "Vehicle") +
          "</span>" +
          '<span class="ridex-gps-vehicle-item__meta">' +
          escapeHtml(vehicle.bookingNumber || "N/A") +
          " • " +
          escapeHtml(vehicle.bookingStatus || "reserved") +
          " • " +
          escapeHtml(vehicle.distanceKm || 0) +
          " km</span>" +
          '<span class="ridex-gps-risk-badge ridex-gps-risk-badge--' +
          getRiskClass(vehicle) +
          '">' +
          escapeHtml(
            vehicle.risk && vehicle.risk.label
              ? vehicle.risk.label
              : "Low Risk",
          ) +
          "</span>" +
          "</button>"
        );
      })
      .join("");

    Array.from(vehicleList.querySelectorAll("[data-gps-vehicle-id]")).forEach(
      function (button) {
        button.addEventListener("click", function () {
          selectVehicle(
            button.dataset.gpsVehicleId,
            button.dataset.gpsBookingId,
          );
        });
      },
    );
  };

  var updateMarkers = function (vehicles) {
    var bounds = [];
    var seenVehicleIds = new Set();

    vehicles.forEach(function (vehicle) {
      if (
        !vehicle.hasGpsSignal ||
        typeof vehicle.lat !== "number" ||
        typeof vehicle.lng !== "number"
      ) {
        return;
      }

      var vehicleId = Number(vehicle.vehicleId);
      seenVehicleIds.add(vehicleId);
      var position = [vehicle.lat, vehicle.lng];
      bounds.push(position);

      if (markers.has(vehicleId)) {
        markers
          .get(vehicleId)
          .setLatLng(position)
          .setIcon(makeMarkerIcon(vehicle))
          .setPopupContent(markerPopupHtml(vehicle));
      } else {
        var marker = L.marker(position, {
          icon: makeMarkerIcon(vehicle),
        })
          .addTo(map)
          .bindPopup(markerPopupHtml(vehicle));

        marker.on("click", function () {
          selectVehicle(vehicleId, vehicle.bookingId);
          var markerPosition = marker.getLatLng();
          var mapsUrl = buildMapsUrl(markerPosition.lat, markerPosition.lng);
          if (mapsUrl) {
            window.open(mapsUrl, "_blank", "noopener");
          }
        });
        markers.set(vehicleId, marker);
      }
    });

    Array.from(markers.keys()).forEach(function (vehicleId) {
      if (!seenVehicleIds.has(vehicleId)) {
        map.removeLayer(markers.get(vehicleId));
        markers.delete(vehicleId);
      }
    });

    if (bounds.length && !selectedVehicleId) {
      map.fitBounds(bounds, {
        padding: [35, 35],
        maxZoom: 13,
      });
    }
  };

  var loadGpsFeed = async function () {
    setStatus("Loading GPS feed...", "is-loading");

    try {
      var response = await fetch(feedUrl, {
        headers: {
          Accept: "application/json",
        },
      });
      var data = await response.json();

      if (!response.ok || !data.ok) {
        throw new Error(data.message || "GPS feed failed.");
      }

      latestVehicles = Array.isArray(data.vehicles) ? data.vehicles : [];
      updateKpis(data.kpis || {});
      updateMarkers(latestVehicles);
      renderVehicleList(latestVehicles);

      if (!selectedVehicleId && !selectedBookingId) {
        if (Number.isFinite(initialBookingId) && initialBookingId > 0) {
          var matchedBooking = latestVehicles.find(function (item) {
            return Number(item.bookingId) === initialBookingId;
          });
          if (matchedBooking) {
            selectedVehicleId = Number(matchedBooking.vehicleId);
            selectedBookingId = Number(matchedBooking.bookingId);
          }
        } else if (Number.isFinite(initialVehicleId) && initialVehicleId > 0) {
          selectedVehicleId = initialVehicleId;
        }
      }

      if (selectedVehicleId || selectedBookingId) {
        selectVehicle(selectedVehicleId, selectedBookingId);
      } else if (latestVehicles.length) {
        selectVehicle(latestVehicles[0].vehicleId, latestVehicles[0].bookingId);
      } else {
        renderSelectedVehicle(null);
      }

      setStatus("Live feed online", "is-ok");
    } catch (error) {
      setStatus(error.message || "GPS feed offline", "is-error");
      if (vehicleList instanceof HTMLElement) {
        vehicleList.innerHTML =
          '<p class="ridex-gps-empty">Unable to load GPS data. Please refresh.</p>';
      }
    }
  };

  var simulateSelectedVehicle = async function () {
    var vehicleId = selectedVehicleId;
    if (!vehicleId && latestVehicles.length) {
      vehicleId = Number(latestVehicles[0].vehicleId);
    }

    if (!vehicleId) {
      setStatus("No active vehicle selected", "is-error");
      return;
    }

    if (simulateButton instanceof HTMLButtonElement) {
      simulateButton.disabled = true;
    }
    setStatus("Updating location...", "is-loading");

    try {
      var response = await fetch(simulateUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ vehicle_id: vehicleId }),
      });
      var data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || "Simulation failed.");
      }

      selectedVehicleId = vehicleId;
      await loadGpsFeed();
      var pointName =
        data && data.point && data.point.pointName
          ? data.point.pointName
          : "next route point";
      setStatus("Simulated movement added: " + pointName, "is-ok");
    } catch (error) {
      setStatus(error.message || "Simulation failed", "is-error");
    } finally {
      if (simulateButton instanceof HTMLButtonElement) {
        simulateButton.disabled = false;
      }
    }
  };

  if (refreshButton instanceof HTMLButtonElement) {
    refreshButton.addEventListener("click", loadGpsFeed);
  }

  if (simulateButton instanceof HTMLButtonElement) {
    simulateButton.addEventListener("click", simulateSelectedVehicle);
  }

  window.setTimeout(function () {
    map.invalidateSize();
  }, 250);

  loadGpsFeed();
  window.setInterval(loadGpsFeed, 15000);
})();
