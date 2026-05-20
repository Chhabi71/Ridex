/**
 * Purpose: Admin booking modal hydration logic extracted from app.js.
 * Website Section: Admin - All Bookings.
 */

(function () {
  "use strict";

  var adminBookingReadModal = document.getElementById(
    "admin-booking-read-modal",
  );
  var adminBookingTrackModal = document.getElementById(
    "admin-booking-track-modal",
  );
  var adminDeleteBookingModal = document.getElementById(
    "admin-delete-booking-modal",
  );

  var adminBookingReadNumberNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-number]")
    : null;
  var adminBookingReadCustomerNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-customer]")
    : null;
  var adminBookingReadPaymentBadgeNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-payment-badge]")
    : null;
  var adminBookingReadPaymentIconNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-payment-icon]")
    : null;
  var adminBookingReadPaymentLabelNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-payment-label]")
    : null;
  var adminBookingReadStatusPill = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-status-pill]")
    : null;
  var adminBookingReadImage = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-image]")
    : null;
  var adminBookingReadVehicleTypeNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-vehicle-type]")
    : null;
  var adminBookingReadVehicleNameNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-vehicle-name]")
    : null;
  var adminBookingReadCustomerPhoneNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-customer-phone]")
    : null;
  var adminBookingReadCustomerEmailNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-customer-email]")
    : null;
  var adminBookingReadDriverIdNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-driver-id]")
    : null;
  var adminBookingReadPickupDateNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-pickup-date]")
    : null;
  var adminBookingReadReturnDateNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-return-date]")
    : null;
  var adminBookingReadReturnTimeNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-return-time]")
    : null;
  var adminBookingReadPricePerDayNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-price-per-day]")
    : null;
  var adminBookingReadDurationLabelNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-duration-label]")
    : null;
  var adminBookingReadDurationPriceNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-duration-price]")
    : null;
  var adminBookingReadDropChargeNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-drop-charge]")
    : null;
  var adminBookingReadLateFeeNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-late-fee]")
    : null;
  var adminBookingReadTaxesFeesNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-taxes-fees]")
    : null;
  var adminBookingReadBillingTotalNode = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-read-billing-total]")
    : null;
  var adminBookingCompleteForm = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-complete-form]")
    : null;
  var adminBookingCompleteIdInput = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-complete-id-input]")
    : null;
  var adminBookingReturnTimeInput = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-return-time-input]")
    : null;
  var adminBookingLateFeePreview = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-late-fee-preview]")
    : null;
  var adminBookingCompleteSubmit = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-complete-submit]")
    : null;
  var adminBookingTrackAction = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-track-action]")
    : null;
  var adminBookingApproveForm = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-approve-form]")
    : null;
  var adminBookingApproveIdInput = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-approve-id-input]")
    : null;
  var adminBookingApproveAction = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-approve-action]")
    : null;
  var adminBookingDeleteAction = adminBookingReadModal
    ? adminBookingReadModal.querySelector("[data-booking-delete-action]")
    : null;

  var adminBookingTrackNumberNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-booking-track-number]")
    : null;
  var adminBookingTrackCustomerNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-booking-track-customer]")
    : null;
  var adminBookingTrackLocationLabelNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector(
        "[data-booking-track-location-label]",
      )
    : null;
  var adminBookingTrackMapEmptyNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-booking-track-map-empty]")
    : null;
  var adminBookingTrackRouteNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-booking-track-route]")
    : null;
  var adminBookingTrackPickupPinNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-booking-track-pickup-pin]")
    : null;
  var adminBookingTrackReturnPinNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-booking-track-return-pin]")
    : null;
  var adminBookingTrackOpenMapsAction = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-booking-track-open-maps]")
    : null;
  var adminBookingTrackPickupLocationNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector(
        "[data-booking-track-pickup-location]",
      )
    : null;
  var adminBookingTrackReturnLocationNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector(
        "[data-booking-track-return-location]",
      )
    : null;
  var adminBookingTrackMapNode = adminBookingTrackModal
    ? adminBookingTrackModal.querySelector("[data-single-booking-gps-map]")
    : null;
  var adminBookingSingleMap = null;
  var adminBookingSingleMarker = null;
  var adminBookingSingleRouteLine = null;
  // risk/safety display removed

  var adminDeleteBookingIdInput = adminDeleteBookingModal
    ? adminDeleteBookingModal.querySelector("[data-delete-booking-id-input]")
    : null;
  var adminDeleteBookingNameNode = adminDeleteBookingModal
    ? adminDeleteBookingModal.querySelector("[data-delete-booking-name]")
    : null;

  var bookingReadContext = null;

  var bookingPaymentClassOptions = [
    "admin-bookings__payment--paid",
    "admin-bookings__payment--pending",
    "admin-bookings__payment--cancelled",
    "admin-bookings__payment--unpaid",
    "admin-bookings__payment--refunded",
    "admin-bookings__payment--unknown",
  ];

  var toTitleCase = function (rawValue) {
    return String(rawValue || "")
      .replace(/[_-]+/g, " ")
      .replace(/\b\w/g, function (character) {
        return character.toUpperCase();
      });
  };

  var parseDateTimeValue = function (rawValue) {
    var normalized = String(rawValue || "").trim();
    if (normalized === "") {
      return null;
    }

    var parsedDate = new Date(
      normalized.includes("T") ? normalized : normalized.replace(" ", "T"),
    );
    if (Number.isNaN(parsedDate.getTime())) {
      return null;
    }

    return parsedDate;
  };

  var formatBookingCurrency = function (rawValue) {
    var numericValue = Number.parseFloat(String(rawValue || "0"));
    return Number.isFinite(numericValue)
      ? "NRs " + numericValue.toFixed(2)
      : "NRs 0.00";
  };

  var normalizeTrackText = function (rawValue, fallbackValue) {
    var fallback =
      typeof fallbackValue === "string" ? fallbackValue : "Unavailable";
    var normalized = String(rawValue || "").trim();
    return normalized !== "" ? normalized : fallback;
  };

  var parseTrackCoordinate = function (rawValue) {
    var numericValue = Number.parseFloat(String(rawValue || "").trim());
    if (!Number.isFinite(numericValue)) {
      return null;
    }

    return numericValue;
  };

  var hasTrackGpsSignal = function (latitude, longitude) {
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
      return false;
    }

    return Math.abs(latitude) > 0.00001 || Math.abs(longitude) > 0.00001;
  };

  var escapeTrackHtml = function (value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };

  var buildTrackPopupHtml = function (
    trackNumber,
    trackCustomer,
    vehicleName,
    locationLabel,
  ) {
    return (
      '<div class="admin-booking-track-modal__popup">' +
      "<strong>" +
      escapeTrackHtml(trackNumber || "Booking") +
      "</strong>" +
      "<span>" +
      escapeTrackHtml(vehicleName || "Vehicle") +
      "</span>" +
      "<span>" +
      escapeTrackHtml(trackCustomer || "Unknown customer") +
      "</span>" +
      "<span>" +
      escapeTrackHtml(locationLabel || "Live GPS") +
      "</span>" +
      "</div>"
    );
  };

  var ensureSingleBookingMap = function () {
    if (!(adminBookingTrackMapNode instanceof HTMLElement)) {
      return false;
    }

    if (typeof L === "undefined") {
      return false;
    }

    adminBookingTrackMapNode.classList.add("is-leaflet-map");

    if (adminBookingSingleMap) {
      return true;
    }

    adminBookingSingleMap = L.map(adminBookingTrackMapNode, {
      scrollWheelZoom: true,
      zoomControl: true,
    }).setView([27.7172, 85.324], 13);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(adminBookingSingleMap);

    return true;
  };

  var renderSingleBookingMap = function (options) {
    options = options || {};

    if (!ensureSingleBookingMap()) {
      return;
    }

    window.setTimeout(function () {
      if (adminBookingSingleMap) {
        adminBookingSingleMap.invalidateSize();
      }
    }, 80);

    if (!hasTrackGpsSignal(options.latitude, options.longitude)) {
      if (adminBookingSingleMarker) {
        adminBookingSingleMap.removeLayer(adminBookingSingleMarker);
        adminBookingSingleMarker = null;
      }
      if (adminBookingSingleRouteLine) {
        adminBookingSingleMap.removeLayer(adminBookingSingleRouteLine);
        adminBookingSingleRouteLine = null;
      }
      return;
    }

    var position = [options.latitude, options.longitude];
    var popupHtml = buildTrackPopupHtml(
      options.trackNumber,
      options.trackCustomer,
      options.vehicleName,
      options.locationLabel,
    );

    var markerIcon = L.divIcon({
      className: "admin-booking-track-modal__leaflet-marker-wrapper",
      html: '<span class="admin-booking-track-modal__leaflet-marker"><span class="material-symbols-rounded">two_wheeler</span></span>',
      iconSize: [42, 42],
      iconAnchor: [21, 21],
      popupAnchor: [0, -18],
    });

    if (adminBookingSingleMarker) {
      adminBookingSingleMarker
        .setLatLng(position)
        .setIcon(markerIcon)
        .setPopupContent(popupHtml);
    } else {
      adminBookingSingleMarker = L.marker(position, { icon: markerIcon })
        .addTo(adminBookingSingleMap)
        .bindPopup(popupHtml);
    }

    if (adminBookingSingleRouteLine) {
      adminBookingSingleMap.removeLayer(adminBookingSingleRouteLine);
      adminBookingSingleRouteLine = null;
    }

    if (Array.isArray(options.route) && options.route.length > 1) {
      var routeCoordinates = options.route
        .filter(function (point) {
          return (
            point &&
            typeof point.lat === "number" &&
            typeof point.lng === "number"
          );
        })
        .map(function (point) {
          return [point.lat, point.lng];
        });

      if (routeCoordinates.length > 1) {
        adminBookingSingleRouteLine = L.polyline(routeCoordinates, {
          weight: 5,
          opacity: 0.78,
        }).addTo(adminBookingSingleMap);
        adminBookingSingleMap.fitBounds(
          adminBookingSingleRouteLine.getBounds(),
          {
            padding: [35, 35],
            maxZoom: 15,
          },
        );
      } else {
        adminBookingSingleMap.setView(position, 15);
      }
    } else {
      adminBookingSingleMap.setView(position, 15);
    }

    adminBookingSingleMarker.openPopup();
  };

  var hydrateTrackFromLiveFeed = function (triggerButton, basePayload) {
    if (!(triggerButton instanceof HTMLElement)) {
      return;
    }

    if (!(adminBookingTrackMapNode instanceof HTMLElement)) {
      return;
    }

    var feedUrl =
      adminBookingTrackMapNode.getAttribute("data-feed-url") ||
      "ajax/gps-live-feed.php";
    var bookingId = Number.parseInt(
      triggerButton.getAttribute("data-booking-track-booking-id") || "0",
      10,
    );
    var vehicleId = Number.parseInt(
      triggerButton.getAttribute("data-booking-track-vehicle-id") || "0",
      10,
    );

    fetch(feedUrl, { headers: { Accept: "application/json" } })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        var vehicles = Array.isArray(data && data.vehicles)
          ? data.vehicles
          : [];
        var vehicle =
          vehicles.find(function (item) {
            return bookingId > 0 && Number(item.bookingId) === bookingId;
          }) ||
          vehicles.find(function (item) {
            return vehicleId > 0 && Number(item.vehicleId) === vehicleId;
          });

        if (
          !vehicle ||
          !vehicle.hasGpsSignal ||
          typeof vehicle.lat !== "number" ||
          typeof vehicle.lng !== "number"
        ) {
          return;
        }

        var locationLabel =
          "" + vehicle.lat.toFixed(5) + ", " + vehicle.lng.toFixed(5);
        var mapsUrl =
          "https://www.google.com/maps/search/?api=1&query=" +
          encodeURIComponent(
            vehicle.lat.toFixed(6) + "," + vehicle.lng.toFixed(6),
          );

        triggerButton.setAttribute(
          "data-booking-track-latitude",
          String(vehicle.lat),
        );
        triggerButton.setAttribute(
          "data-booking-track-longitude",
          String(vehicle.lng),
        );
        triggerButton.setAttribute(
          "data-booking-track-location-label",
          locationLabel,
        );
        triggerButton.setAttribute("data-booking-track-map-url", mapsUrl);
        triggerButton.setAttribute("data-booking-track-has-signal", "true");

        if (adminBookingTrackLocationLabelNode instanceof HTMLElement) {
          adminBookingTrackLocationLabelNode.textContent = locationLabel;
        }

        if (adminBookingTrackOpenMapsAction instanceof HTMLAnchorElement) {
          adminBookingTrackOpenMapsAction.href = mapsUrl;
          adminBookingTrackOpenMapsAction.classList.remove("is-disabled");
          adminBookingTrackOpenMapsAction.removeAttribute("aria-disabled");
          adminBookingTrackOpenMapsAction.removeAttribute("tabindex");
        }

        if (adminBookingTrackMapEmptyNode instanceof HTMLElement) {
          adminBookingTrackMapEmptyNode.hidden = true;
        }

        renderSingleBookingMap({
          latitude: vehicle.lat,
          longitude: vehicle.lng,
          trackNumber: vehicle.bookingNumber || basePayload.trackNumber,
          trackCustomer: vehicle.customerName || basePayload.trackCustomer,
          vehicleName: vehicle.vehicleName || basePayload.vehicleName,
          locationLabel: locationLabel,
          route: Array.isArray(vehicle.route) ? vehicle.route : [],
        });
      })
      .catch(function () {
        // Keep the coordinate snapshot already passed from the booking row.
      });
  };

  var calculateBookingTaxesAndTotal = function (
    durationPrice,
    dropCharge,
    lateFee,
    taxRate,
  ) {
    var resolvedTaxRate = Number.isFinite(taxRate) ? taxRate : 0.13;
    var normalizedDurationPrice = Number.isFinite(durationPrice)
      ? durationPrice
      : 0;
    var normalizedDropCharge = Number.isFinite(dropCharge) ? dropCharge : 0;
    var normalizedLateFee = Number.isFinite(lateFee) ? lateFee : 0;

    var taxBase = Math.max(
      0,
      normalizedDurationPrice + normalizedDropCharge + normalizedLateFee,
    );
    var taxes = taxBase * resolvedTaxRate;
    var total = taxBase + taxes;

    return {
      taxes: taxes,
      total: total,
    };
  };

  var toDateTimeLocalInputValue = function (rawValue) {
    var parsedDate = parseDateTimeValue(rawValue);
    if (!parsedDate) {
      return "";
    }

    var year = parsedDate.getFullYear();
    var month = String(parsedDate.getMonth() + 1).padStart(2, "0");
    var day = String(parsedDate.getDate()).padStart(2, "0");
    var hours = String(parsedDate.getHours()).padStart(2, "0");
    var minutes = String(parsedDate.getMinutes()).padStart(2, "0");
    return year + "-" + month + "-" + day + " " + hours + ":" + minutes;
  };

  var readBookingBoolean = function (triggerButton, attributeName) {
    return (
      String(
        triggerButton.getAttribute(attributeName) || "false",
      ).toLowerCase() === "true"
    );
  };

  var resetBookingReadModal = function () {
    bookingReadContext = null;

    if (adminBookingCompleteForm instanceof HTMLFormElement) {
      adminBookingCompleteForm.reset();
      adminBookingCompleteForm.hidden = true;
    }

    if (adminBookingReturnTimeInput instanceof HTMLInputElement) {
      adminBookingReturnTimeInput.required = false;
      adminBookingReturnTimeInput.setCustomValidity("");
      if (adminBookingReturnTimeInput._flatpickr) {
        adminBookingReturnTimeInput._flatpickr.clear();
      }
    }

    if (adminBookingReadReturnTimeNode instanceof HTMLElement) {
      adminBookingReadReturnTimeNode.textContent = "N/A";
    }

    if (adminBookingReadStatusPill instanceof HTMLElement) {
      adminBookingReadStatusPill.className =
        "admin-booking-read-modal__status-pill admin-booking-read-modal__status-pill--unknown";
      adminBookingReadStatusPill.textContent = "Unknown";
    }

    if (adminBookingReadPaymentBadgeNode instanceof HTMLElement) {
      adminBookingReadPaymentBadgeNode.className =
        "admin-booking-read-modal__payment admin-bookings__payment admin-bookings__payment--unknown";
    }

    if (adminBookingReadPaymentIconNode instanceof HTMLElement) {
      adminBookingReadPaymentIconNode.textContent = "help";
    }

    if (adminBookingReadPaymentLabelNode instanceof HTMLElement) {
      adminBookingReadPaymentLabelNode.textContent = "Unknown";
    }

    if (adminBookingTrackAction instanceof HTMLElement) {
      adminBookingTrackAction.hidden = true;
      adminBookingTrackAction.classList.remove("is-disabled");
      adminBookingTrackAction.removeAttribute("aria-disabled");
      adminBookingTrackAction.removeAttribute("tabindex");
      adminBookingTrackAction.removeAttribute("data-booking-track-number");
      adminBookingTrackAction.removeAttribute("data-booking-track-customer");
      adminBookingTrackAction.removeAttribute("data-booking-track-location");
      adminBookingTrackAction.removeAttribute("data-booking-track-pickup");
      adminBookingTrackAction.removeAttribute("data-booking-track-return");
      // risk/safety attributes removed
      adminBookingTrackAction.removeAttribute("data-booking-track-map-url");
      adminBookingTrackAction.removeAttribute("data-booking-track-has-signal");
      adminBookingTrackAction.removeAttribute("data-booking-track-vehicle-id");
      adminBookingTrackAction.removeAttribute("data-booking-track-booking-id");
    }

    if (adminBookingApproveForm instanceof HTMLElement) {
      adminBookingApproveForm.hidden = true;
    }

    if (adminBookingDeleteAction instanceof HTMLElement) {
      adminBookingDeleteAction.hidden = true;
    }

    if (adminBookingCompleteSubmit instanceof HTMLButtonElement) {
      adminBookingCompleteSubmit.disabled = true;
      adminBookingCompleteSubmit.hidden = true;
    }

    if (adminBookingLateFeePreview instanceof HTMLElement) {
      adminBookingLateFeePreview.textContent = "Late fee: NRs 0.00 (0h x $10)";
      adminBookingLateFeePreview.hidden = true;
    }
  };

  var validateAdminReturnTimeInput = function (shouldReport) {
    if (!(adminBookingReturnTimeInput instanceof HTMLInputElement)) {
      return true;
    }

    var shouldReportValidity = shouldReport === true;
    adminBookingReturnTimeInput.setCustomValidity("");

    if (adminBookingReturnTimeInput.required !== true) {
      return true;
    }

    var selectedReturnDate = parseDateTimeValue(
      adminBookingReturnTimeInput.value,
    );
    if (!(selectedReturnDate instanceof Date)) {
      adminBookingReturnTimeInput.setCustomValidity(
        "Please enter a valid return date/time.",
      );
      if (shouldReportValidity) {
        adminBookingReturnTimeInput.reportValidity();
      }
      return false;
    }

    var nowDate = new Date();
    if (selectedReturnDate > nowDate) {
      adminBookingReturnTimeInput.setCustomValidity(
        "Return time cannot be in the future.",
      );
      if (shouldReportValidity) {
        adminBookingReturnTimeInput.reportValidity();
      }
      return false;
    }

    var pickupDate = bookingReadContext
      ? parseDateTimeValue(bookingReadContext.pickupDatetime)
      : null;
    if (pickupDate instanceof Date && selectedReturnDate < pickupDate) {
      adminBookingReturnTimeInput.setCustomValidity(
        "Return time cannot be before pickup time.",
      );
      if (shouldReportValidity) {
        adminBookingReturnTimeInput.reportValidity();
      }
      return false;
    }

    return true;
  };

  var updateBookingLateFeePreview = function () {
    if (
      !(adminBookingLateFeePreview instanceof HTMLElement) ||
      !(adminBookingReturnTimeInput instanceof HTMLInputElement) ||
      !bookingReadContext ||
      bookingReadContext.allowLateFeePreview !== true
    ) {
      return;
    }

    if (!validateAdminReturnTimeInput(false)) {
      adminBookingLateFeePreview.hidden = true;
      return;
    }

    var scheduledReturnDate = parseDateTimeValue(
      bookingReadContext.scheduledReturnDatetime,
    );
    var selectedReturnDate = parseDateTimeValue(
      adminBookingReturnTimeInput.value,
    );

    var lateHours = 0;
    if (
      scheduledReturnDate instanceof Date &&
      selectedReturnDate instanceof Date &&
      selectedReturnDate > scheduledReturnDate
    ) {
      lateHours = Math.floor(
        (selectedReturnDate.getTime() - scheduledReturnDate.getTime()) /
          (1000 * 60 * 60),
      );
      if (!Number.isFinite(lateHours) || lateHours < 0) {
        lateHours = 0;
      }
    }

    var lateFee = lateHours * 10;
    adminBookingLateFeePreview.textContent =
      "Late fee: $" + lateFee.toFixed(2) + " (" + lateHours + "h x $10)";
    adminBookingLateFeePreview.hidden = false;

    if (adminBookingReadLateFeeNode instanceof HTMLElement) {
      adminBookingReadLateFeeNode.textContent = formatBookingCurrency(lateFee);
    }

    var billingSummary = calculateBookingTaxesAndTotal(
      bookingReadContext.durationPrice,
      bookingReadContext.dropCharge,
      lateFee,
      bookingReadContext.taxRate,
    );

    if (adminBookingReadTaxesFeesNode instanceof HTMLElement) {
      adminBookingReadTaxesFeesNode.textContent = formatBookingCurrency(
        billingSummary.taxes,
      );
    }

    if (adminBookingReadBillingTotalNode instanceof HTMLElement) {
      adminBookingReadBillingTotalNode.textContent = formatBookingCurrency(
        billingSummary.total,
      );
    }
  };

  var initAdminBookingReturnTimePicker = function () {
    if (!(adminBookingReturnTimeInput instanceof HTMLInputElement)) {
      return;
    }

    if (typeof window.flatpickr !== "function") {
      return;
    }

    if (adminBookingReturnTimeInput._flatpickr) {
      return;
    }

    window.flatpickr(adminBookingReturnTimeInput, {
      allowInput: true,
      disableMobile: true,
      static: true,
      monthSelectorType: "static",
      enableTime: true,
      noCalendar: false,
      dateFormat: "Y-m-d H:i",
      altInput: true,
      altInputClass: "admin-booking-read-modal__input",
      altFormat: "d/m/Y h:i K",
      minuteIncrement: 5,
      maxDate: "today",
      onReady: function (_selectedDates, _dateStr, instance) {
        if (instance.altInput instanceof HTMLInputElement) {
          instance.altInput.placeholder =
            adminBookingReturnTimeInput.getAttribute("placeholder") ||
            "dd/mm/yyyy  --:-- --";
        }
      },
      onOpen: function (_selectedDates, _dateStr, instance) {
        instance.set("maxDate", new Date());
        if (instance.calendarContainer instanceof HTMLElement) {
          instance.calendarContainer.classList.add(
            "admin-booking-return-flatpickr",
          );
        }
      },
      onValueUpdate: function () {
        validateAdminReturnTimeInput(false);
        updateBookingLateFeePreview();
      },
      prevArrow:
        '<span class="material-symbols-rounded" aria-hidden="true">chevron_left</span>',
      nextArrow:
        '<span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>',
    });
  };

  var hydrateBookingReadModal = function (triggerButton) {
    if (!(triggerButton instanceof HTMLElement)) {
      return;
    }

    var bookingId = Number.parseInt(
      triggerButton.getAttribute("data-booking-id") || "0",
      10,
    );
    var bookingDisplayId =
      triggerButton.getAttribute("data-booking-display-id") || "#N/A";
    var customerName =
      triggerButton.getAttribute("data-booking-customer-name") || "Unknown";
    var customerPhone =
      triggerButton.getAttribute("data-booking-customer-phone") || "N/A";
    var customerEmail =
      triggerButton.getAttribute("data-booking-customer-email") || "N/A";
    var driverId =
      triggerButton.getAttribute("data-booking-driver-id") || "N/A";
    var vehicleId = Number.parseInt(
      triggerButton.getAttribute("data-booking-vehicle-id") || "0",
      10,
    );
    if (!Number.isFinite(vehicleId)) {
      vehicleId = 0;
    }
    var vehicleName =
      triggerButton.getAttribute("data-booking-vehicle-name") || "Vehicle";
    var vehicleType =
      triggerButton.getAttribute("data-booking-vehicle-type") || "car";
    var vehicleStatusKey = (
      triggerButton.getAttribute("data-booking-vehicle-status") || "unknown"
    ).toLowerCase();
    var vehicleStatusLabel =
      triggerButton.getAttribute("data-booking-vehicle-status-label") ||
      toTitleCase(vehicleStatusKey.replace("_", " "));
    var vehicleImage =
      triggerButton.getAttribute("data-booking-vehicle-image") ||
      "images/vehicle-feature.png";
    var pickupDate =
      triggerButton.getAttribute("data-booking-pickup-date") || "N/A";
    var returnDate =
      triggerButton.getAttribute("data-booking-return-date") || "N/A";
    var returnTimeDisplay =
      triggerButton.getAttribute("data-booking-return-time-display") || "N/A";
    var pickupDatetime =
      triggerButton.getAttribute("data-booking-pickup-datetime") || "";
    var scheduledReturnDatetime =
      triggerButton.getAttribute("data-booking-return-datetime") || "";
    var paymentStatusLabel =
      triggerButton.getAttribute("data-booking-payment-label") || "N/A";
    var paymentStatusIcon =
      triggerButton.getAttribute("data-booking-payment-icon") || "help";
    var paymentStatusClass =
      triggerButton.getAttribute("data-booking-payment-class") ||
      "admin-bookings__payment--unknown";
    var pricePerDay = Number.parseFloat(
      triggerButton.getAttribute("data-booking-price-per-day") || "0",
    );
    var durationDays = Number.parseInt(
      triggerButton.getAttribute("data-booking-duration-days") || "1",
      10,
    );
    var durationLabel =
      triggerButton.getAttribute("data-booking-duration-label") ||
      "Price for 1 day";
    var durationPrice = Number.parseFloat(
      triggerButton.getAttribute("data-booking-duration-price") || "0",
    );
    var dropCharge = Number.parseFloat(
      triggerButton.getAttribute("data-booking-drop-charge") || "0",
    );
    var existingLateFee = Number.parseFloat(
      triggerButton.getAttribute("data-booking-late-fee") || "0",
    );
    var lateFeeNotApplicable = readBookingBoolean(
      triggerButton,
      "data-booking-late-fee-na",
    );
    var taxesFees = Number.parseFloat(
      triggerButton.getAttribute("data-booking-taxes-fees") || "0",
    );
    var billingTotal = Number.parseFloat(
      triggerButton.getAttribute("data-booking-billing-total") || "0",
    );
    var returnTimeInputValue =
      triggerButton.getAttribute("data-booking-return-time-input") ||
      toDateTimeLocalInputValue(scheduledReturnDatetime);
    var trackPickupLocation = normalizeTrackText(
      triggerButton.getAttribute("data-booking-pickup-location"),
    );
    var trackReturnLocation = normalizeTrackText(
      triggerButton.getAttribute("data-booking-return-location"),
    );
    var trackMapUrl = String(
      triggerButton.getAttribute("data-booking-track-map-url") || "",
    ).trim();
    var trackLatitude = parseTrackCoordinate(
      triggerButton.getAttribute("data-booking-gps-latitude"),
    );
    var trackLongitude = parseTrackCoordinate(
      triggerButton.getAttribute("data-booking-gps-longitude"),
    );
    var trackHasSignal =
      hasTrackGpsSignal(trackLatitude, trackLongitude) ||
      readBookingBoolean(triggerButton, "data-booking-track-has-signal");

    var canTrack = readBookingBoolean(triggerButton, "data-booking-can-track");
    var isTrackDisabled = readBookingBoolean(
      triggerButton,
      "data-booking-track-disabled",
    );
    var canCompleteReturn = readBookingBoolean(
      triggerButton,
      "data-booking-can-complete",
    );
    var canApproveCancellation = readBookingBoolean(
      triggerButton,
      "data-booking-can-approve-cancellation",
    );
    var canDelete = readBookingBoolean(
      triggerButton,
      "data-booking-can-delete",
    );

    if (adminBookingReadNumberNode instanceof HTMLElement) {
      adminBookingReadNumberNode.textContent = bookingDisplayId;
    }

    if (adminBookingReadCustomerNode instanceof HTMLElement) {
      adminBookingReadCustomerNode.textContent = customerName;
    }

    if (adminBookingReadPaymentBadgeNode instanceof HTMLElement) {
      var sanitizedPaymentClass = bookingPaymentClassOptions.includes(
        paymentStatusClass,
      )
        ? paymentStatusClass
        : "admin-bookings__payment--unknown";
      adminBookingReadPaymentBadgeNode.className =
        "admin-booking-read-modal__payment admin-bookings__payment";
      adminBookingReadPaymentBadgeNode.classList.add(sanitizedPaymentClass);
    }

    if (adminBookingReadPaymentIconNode instanceof HTMLElement) {
      adminBookingReadPaymentIconNode.textContent = paymentStatusIcon;
    }

    if (adminBookingReadPaymentLabelNode instanceof HTMLElement) {
      adminBookingReadPaymentLabelNode.textContent = paymentStatusLabel;
    }

    if (adminBookingReadStatusPill instanceof HTMLElement) {
      var statusPillKey = [
        "available",
        "unavailable",
        "reserved",
        "on_trip",
        "overdue",
        "maintenance",
        "ready",
      ].includes(vehicleStatusKey)
        ? vehicleStatusKey
        : "available";

      var normalizedVehicleStatusLabel =
        String(vehicleStatusLabel || "").trim() ||
        (statusPillKey === "unavailable" ? "Unavailable" : "Available");

      adminBookingReadStatusPill.textContent = normalizedVehicleStatusLabel;
      adminBookingReadStatusPill.className =
        "admin-booking-read-modal__status-pill admin-booking-read-modal__status-pill--" +
        statusPillKey.replace("_", "-");
    }

    if (adminBookingReadImage instanceof HTMLImageElement) {
      adminBookingReadImage.src = vehicleImage;
      adminBookingReadImage.alt = vehicleName;
    }

    if (adminBookingReadVehicleTypeNode instanceof HTMLElement) {
      adminBookingReadVehicleTypeNode.textContent = toTitleCase(
        String(vehicleType).replace(/s$/, ""),
      );
    }

    if (adminBookingReadVehicleNameNode instanceof HTMLElement) {
      adminBookingReadVehicleNameNode.textContent = vehicleName;
    }

    if (adminBookingReadCustomerPhoneNode instanceof HTMLElement) {
      adminBookingReadCustomerPhoneNode.textContent = customerPhone || "N/A";
    }

    if (adminBookingReadCustomerEmailNode instanceof HTMLElement) {
      adminBookingReadCustomerEmailNode.textContent = customerEmail || "N/A";
    }

    if (adminBookingReadDriverIdNode instanceof HTMLElement) {
      adminBookingReadDriverIdNode.textContent = driverId || "N/A";
    }

    if (adminBookingReadPickupDateNode instanceof HTMLElement) {
      adminBookingReadPickupDateNode.textContent = pickupDate;
    }

    if (adminBookingReadReturnDateNode instanceof HTMLElement) {
      adminBookingReadReturnDateNode.textContent = returnDate;
    }

    if (adminBookingReadReturnTimeNode instanceof HTMLElement) {
      adminBookingReadReturnTimeNode.textContent = returnTimeDisplay;
    }

    if (adminBookingReadPricePerDayNode instanceof HTMLElement) {
      adminBookingReadPricePerDayNode.textContent =
        formatBookingCurrency(pricePerDay);
    }

    if (adminBookingReadDurationLabelNode instanceof HTMLElement) {
      var normalizedDays =
        Number.isFinite(durationDays) && durationDays > 0 ? durationDays : 1;
      adminBookingReadDurationLabelNode.textContent =
        durationLabel ||
        (normalizedDays === 1
          ? "Price for 1 day"
          : "Price for " + normalizedDays + " days");
    }

    if (adminBookingReadDurationPriceNode instanceof HTMLElement) {
      adminBookingReadDurationPriceNode.textContent =
        formatBookingCurrency(durationPrice);
    }

    if (adminBookingReadDropChargeNode instanceof HTMLElement) {
      adminBookingReadDropChargeNode.textContent =
        formatBookingCurrency(dropCharge);
    }

    if (adminBookingReadLateFeeNode instanceof HTMLElement) {
      adminBookingReadLateFeeNode.textContent =
        lateFeeNotApplicable && !canCompleteReturn
          ? "N/A"
          : formatBookingCurrency(existingLateFee);
    }

    if (adminBookingReadTaxesFeesNode instanceof HTMLElement) {
      adminBookingReadTaxesFeesNode.textContent =
        formatBookingCurrency(taxesFees);
    }

    if (adminBookingReadBillingTotalNode instanceof HTMLElement) {
      adminBookingReadBillingTotalNode.textContent =
        formatBookingCurrency(billingTotal);
    }

    if (adminBookingCompleteIdInput instanceof HTMLInputElement) {
      adminBookingCompleteIdInput.value =
        bookingId > 0 ? String(bookingId) : "";
    }

    if (adminBookingReturnTimeInput instanceof HTMLInputElement) {
      adminBookingReturnTimeInput.value = returnTimeInputValue;
      adminBookingReturnTimeInput.required = canCompleteReturn;
      adminBookingReturnTimeInput.setCustomValidity("");

      if (adminBookingReturnTimeInput._flatpickr) {
        var parsedPickupDate = parseDateTimeValue(pickupDatetime);
        if (parsedPickupDate instanceof Date) {
          adminBookingReturnTimeInput._flatpickr.set(
            "minDate",
            parsedPickupDate,
          );
        } else {
          adminBookingReturnTimeInput._flatpickr.set("minDate", null);
        }
        adminBookingReturnTimeInput._flatpickr.set("maxDate", new Date());

        var parsedReturnDate = parseDateTimeValue(returnTimeInputValue);
        if (parsedReturnDate instanceof Date) {
          adminBookingReturnTimeInput._flatpickr.setDate(
            parsedReturnDate,
            false,
          );
        } else {
          adminBookingReturnTimeInput._flatpickr.clear();
        }
      }
    }

    if (adminBookingCompleteForm instanceof HTMLElement) {
      adminBookingCompleteForm.hidden = !canCompleteReturn;
    }

    if (adminBookingCompleteSubmit instanceof HTMLButtonElement) {
      adminBookingCompleteSubmit.disabled = !canCompleteReturn;
      adminBookingCompleteSubmit.hidden = !canCompleteReturn;
    }

    if (adminBookingLateFeePreview instanceof HTMLElement) {
      adminBookingLateFeePreview.hidden = !canCompleteReturn;
    }

    if (adminBookingTrackAction instanceof HTMLElement) {
      adminBookingTrackAction.hidden = !canTrack;

      if (!canTrack) {
        adminBookingTrackAction.classList.remove("is-disabled");
        adminBookingTrackAction.removeAttribute("aria-disabled");
        adminBookingTrackAction.removeAttribute("tabindex");
      } else if (isTrackDisabled) {
        adminBookingTrackAction.classList.add("is-disabled");
        adminBookingTrackAction.setAttribute("aria-disabled", "true");
        adminBookingTrackAction.setAttribute("tabindex", "-1");
      } else {
        adminBookingTrackAction.classList.remove("is-disabled");
        adminBookingTrackAction.removeAttribute("aria-disabled");
        adminBookingTrackAction.removeAttribute("tabindex");

        adminBookingTrackAction.setAttribute(
          "data-booking-track-number",
          bookingDisplayId,
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-customer",
          customerName,
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-location",
          triggerButton.getAttribute("data-booking-track-location-label") ||
            (trackHasSignal ? "Live GPS available" : "No GPS signal"),
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-location-label",
          triggerButton.getAttribute("data-booking-track-location-label") ||
            (trackHasSignal ? "Live GPS available" : "No GPS signal"),
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-pickup",
          trackPickupLocation,
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-return",
          trackReturnLocation,
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-map-url",
          trackMapUrl,
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-has-signal",
          trackHasSignal ? "true" : "false",
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-vehicle-id",
          vehicleId > 0 ? String(vehicleId) : "",
        );
        adminBookingTrackAction.setAttribute(
          "data-booking-track-booking-id",
          bookingId > 0 ? String(bookingId) : "",
        );
        if (trackLatitude !== null) {
          adminBookingTrackAction.setAttribute(
            "data-booking-track-latitude",
            String(trackLatitude),
          );
        } else {
          adminBookingTrackAction.removeAttribute(
            "data-booking-track-latitude",
          );
        }

        if (trackLongitude !== null) {
          adminBookingTrackAction.setAttribute(
            "data-booking-track-longitude",
            String(trackLongitude),
          );
        } else {
          adminBookingTrackAction.removeAttribute(
            "data-booking-track-longitude",
          );
        }
      }
    }

    if (adminBookingApproveIdInput instanceof HTMLInputElement) {
      adminBookingApproveIdInput.value = bookingId > 0 ? String(bookingId) : "";
    }

    if (adminBookingApproveForm instanceof HTMLElement) {
      adminBookingApproveForm.hidden = !canApproveCancellation;
      adminBookingApproveForm.style.display = canApproveCancellation
        ? ""
        : "none";
    }

    if (adminBookingApproveAction instanceof HTMLButtonElement) {
      adminBookingApproveAction.disabled = !canApproveCancellation;
      adminBookingApproveAction.hidden = !canApproveCancellation;
    }

    if (adminBookingDeleteAction instanceof HTMLElement) {
      adminBookingDeleteAction.hidden = !canDelete;
      adminBookingDeleteAction.setAttribute(
        "data-delete-booking-id",
        bookingId > 0 ? String(bookingId) : "",
      );
      adminBookingDeleteAction.setAttribute(
        "data-delete-booking-label",
        bookingDisplayId,
      );
    }

    bookingReadContext = {
      pickupDatetime: pickupDatetime,
      scheduledReturnDatetime: scheduledReturnDatetime,
      durationPrice: Number.isFinite(durationPrice) ? durationPrice : 0,
      dropCharge: Number.isFinite(dropCharge) ? dropCharge : 0,
      taxRate: 0.13,
      allowLateFeePreview: canCompleteReturn,
    };

    if (canCompleteReturn) {
      validateAdminReturnTimeInput(false);
      updateBookingLateFeePreview();
    } else if (adminBookingLateFeePreview instanceof HTMLElement) {
      adminBookingLateFeePreview.textContent = lateFeeNotApplicable
        ? "Late fee: N/A"
        : "Late fee: " + formatBookingCurrency(existingLateFee);
      adminBookingLateFeePreview.hidden = true;
    }
  };

  var hydrateBookingTrackModal = function (triggerButton) {
    if (!(triggerButton instanceof HTMLElement)) {
      return;
    }

    var trackNumber = normalizeTrackText(
      triggerButton.getAttribute("data-booking-track-number"),
      "#N/A",
    );
    var trackCustomer = normalizeTrackText(
      triggerButton.getAttribute("data-booking-track-customer"),
      "Unknown",
    );
    var trackLocation = normalizeTrackText(
      triggerButton.getAttribute("data-booking-track-location-label") ||
        triggerButton.getAttribute("data-booking-track-location"),
      "No GPS signal",
    );
    var trackPickup = normalizeTrackText(
      triggerButton.getAttribute("data-booking-track-pickup"),
      "Unavailable",
    );
    var trackReturn = normalizeTrackText(
      triggerButton.getAttribute("data-booking-track-return"),
      "Unavailable",
    );
    var trackMapUrl = String(
      triggerButton.getAttribute("data-booking-track-map-url") || "",
    ).trim();
    var hasSignal = readBookingBoolean(
      triggerButton,
      "data-booking-track-has-signal",
    );
    var trackLatitude = parseTrackCoordinate(
      triggerButton.getAttribute("data-booking-track-latitude") ||
        triggerButton.getAttribute("data-booking-gps-latitude"),
    );
    var trackLongitude = parseTrackCoordinate(
      triggerButton.getAttribute("data-booking-track-longitude") ||
        triggerButton.getAttribute("data-booking-gps-longitude"),
    );
    var trackVehicleName = normalizeTrackText(
      triggerButton.getAttribute("data-booking-vehicle-name"),
      "Vehicle",
    );
    hasSignal = hasSignal || hasTrackGpsSignal(trackLatitude, trackLongitude);

    if (adminBookingTrackNumberNode instanceof HTMLElement) {
      adminBookingTrackNumberNode.textContent = trackNumber;
    }

    if (adminBookingTrackCustomerNode instanceof HTMLElement) {
      adminBookingTrackCustomerNode.textContent = trackCustomer;
    }

    if (adminBookingTrackLocationLabelNode instanceof HTMLElement) {
      adminBookingTrackLocationLabelNode.textContent = trackLocation;
    }

    if (adminBookingTrackPickupLocationNode instanceof HTMLElement) {
      adminBookingTrackPickupLocationNode.textContent = trackPickup;
    }

    if (adminBookingTrackReturnLocationNode instanceof HTMLElement) {
      adminBookingTrackReturnLocationNode.textContent = trackReturn;
    }
    // risk/safety UI removed

    var hasMapTarget = hasSignal && trackMapUrl !== "";
    if (adminBookingTrackOpenMapsAction instanceof HTMLAnchorElement) {
      if (hasMapTarget) {
        adminBookingTrackOpenMapsAction.href = trackMapUrl;
        adminBookingTrackOpenMapsAction.classList.remove("is-disabled");
        adminBookingTrackOpenMapsAction.removeAttribute("aria-disabled");
        adminBookingTrackOpenMapsAction.removeAttribute("tabindex");
      } else {
        adminBookingTrackOpenMapsAction.href = "#";
        adminBookingTrackOpenMapsAction.classList.add("is-disabled");
        adminBookingTrackOpenMapsAction.setAttribute("aria-disabled", "true");
        adminBookingTrackOpenMapsAction.setAttribute("tabindex", "-1");
      }
    }

    if (adminBookingTrackMapEmptyNode instanceof HTMLElement) {
      adminBookingTrackMapEmptyNode.hidden = hasSignal;
    }

    if (adminBookingTrackRouteNode instanceof HTMLElement) {
      adminBookingTrackRouteNode.hidden = !hasSignal;
    }

    if (adminBookingTrackPickupPinNode instanceof HTMLElement) {
      adminBookingTrackPickupPinNode.hidden = !hasSignal;
    }

    if (adminBookingTrackReturnPinNode instanceof HTMLElement) {
      adminBookingTrackReturnPinNode.hidden = !hasSignal;
    }

    renderSingleBookingMap({
      latitude: trackLatitude,
      longitude: trackLongitude,
      trackNumber: trackNumber,
      trackCustomer: trackCustomer,
      vehicleName: trackVehicleName,
      locationLabel: trackLocation,
      route: [],
    });

    hydrateTrackFromLiveFeed(triggerButton, {
      trackNumber: trackNumber,
      trackCustomer: trackCustomer,
      vehicleName: trackVehicleName,
    });
  };

  var hydrateDeleteBookingModal = function (triggerButton) {
    if (!(triggerButton instanceof HTMLElement)) {
      return;
    }

    var bookingId = Number.parseInt(
      triggerButton.getAttribute("data-delete-booking-id") ||
        triggerButton.getAttribute("data-booking-id") ||
        "0",
      10,
    );
    var bookingLabel =
      triggerButton.getAttribute("data-delete-booking-label") ||
      triggerButton.getAttribute("data-booking-display-id") ||
      "this booking";

    if (adminDeleteBookingIdInput instanceof HTMLInputElement) {
      adminDeleteBookingIdInput.value =
        Number.isFinite(bookingId) && bookingId > 0 ? String(bookingId) : "";
    }

    if (adminDeleteBookingNameNode instanceof HTMLElement) {
      adminDeleteBookingNameNode.textContent = bookingLabel;
    }
  };

  // Track stays inside All Bookings and opens the single-vehicle GPS modal.
  // The separate Live Tracking page remains only for viewing all vehicles together.

  if (adminBookingTrackAction instanceof HTMLElement) {
    adminBookingTrackAction.addEventListener("click", function (event) {
      // Keep single-vehicle tracking inside the All Bookings page.
      // This prevents any accidental navigation to the all-vehicles Live Tracking page.
      event.preventDefault();

      if (
        adminBookingTrackAction.classList.contains("is-disabled") ||
        adminBookingTrackAction.hidden
      ) {
        event.stopPropagation();
        return;
      }

      hydrateBookingTrackModal(adminBookingTrackAction);
    });
  }

  window.RidexBookingModals = {
    initAdminBookingReturnTimePicker: initAdminBookingReturnTimePicker,
    validateAdminReturnTimeInput: validateAdminReturnTimeInput,
    resetBookingReadModal: resetBookingReadModal,
    updateBookingLateFeePreview: updateBookingLateFeePreview,
    hydrateBookingReadModal: hydrateBookingReadModal,
    hydrateBookingTrackModal: hydrateBookingTrackModal,
    hydrateDeleteBookingModal: hydrateDeleteBookingModal,
  };
})();
