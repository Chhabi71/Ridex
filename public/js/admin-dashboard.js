/**
 * Purpose: Admin dashboard JS to load metrics and render charts/widgets.
 * Website Section: Admin Dashboard.
 */

(function () {
  "use strict";

  // Mobile admin sidebar: toggle with header logo tap (runs on all admin pages)
  (function initAdminSidebarMobileToggle() {
    var sidebar = document.querySelector(".admin-sidebar");
    var logo = document.querySelector(".site-header__logo");

    if (!sidebar || !logo) {
      return;
    }

    var mediaQuery = window.matchMedia("(max-width: 900px)");

    var closeSidebar = function () {
      sidebar.classList.remove("open");
    };

    logo.style.cursor = "pointer";
    logo.setAttribute("role", "button");
    logo.setAttribute("aria-label", "Toggle admin sidebar");
    logo.setAttribute("tabindex", "0");

    logo.addEventListener("click", function (event) {
      if (!mediaQuery.matches) {
        return;
      }

      // Prevent navigation when logo is an anchor so toggle is visible
      if (event && typeof event.preventDefault === "function") {
        event.preventDefault();
      }

      sidebar.classList.toggle("open");
    });

    logo.addEventListener("keydown", function (event) {
      if (event.key !== "Enter" && event.key !== " ") {
        return;
      }

      if (!mediaQuery.matches) {
        return;
      }

      // Prevent default (e.g., link activation) and toggle
      if (event && typeof event.preventDefault === "function") {
        event.preventDefault();
      }

      sidebar.classList.toggle("open");
    });

    document.addEventListener("click", function (event) {
      if (!mediaQuery.matches) {
        closeSidebar();
        return;
      }

      if (sidebar.contains(event.target) || logo.contains(event.target)) {
        return;
      }

      closeSidebar();
    });

    window.addEventListener("resize", function () {
      if (!mediaQuery.matches) {
        closeSidebar();
      }
    });
  })();

  var dataNode = document.getElementById("admin-dashboard-data");
  if (!dataNode) {
    return;
  }

  var trendClassOptions = [
    "admin-kpi-card__trend--up",
    "admin-kpi-card__trend--down",
    "admin-kpi-card__trend--flat",
    "admin-kpi-card__trend--unavailable",
  ];
  var salesChartInstance = null;
  var rentChartInstance = null;
  var refreshInFlight = false;

  var parseJsonText = function (rawText) {
    try {
      return JSON.parse(rawText || "{}");
    } catch (error) {
      return {};
    }
  };

  var isFiniteNumber = function (value) {
    if (value === null || value === undefined || value === "") {
      return false;
    }

    var numericValue = Number(value);
    return Number.isFinite(numericValue);
  };

  var normalizePayload = function (payload) {
    var normalized = {
      kpis: {},
      charts: {},
    };

    if (!payload || typeof payload !== "object" || Array.isArray(payload)) {
      return normalized;
    }

    var hasKpis =
      payload.kpis &&
      typeof payload.kpis === "object" &&
      !Array.isArray(payload.kpis);
    var hasCharts =
      payload.charts &&
      typeof payload.charts === "object" &&
      !Array.isArray(payload.charts);

    if (hasKpis) {
      normalized.kpis = payload.kpis;
    }

    if (hasCharts) {
      normalized.charts = payload.charts;
    }

    if (!hasKpis && !hasCharts) {
      normalized.charts = payload;
    }

    return normalized;
  };

  var setUnavailableState = function (panelKey, isUnavailable) {
    var panel = document.querySelector('[data-chart-panel="' + panelKey + '"]');
    if (!panel) {
      return;
    }

    var fallback = panel.querySelector("[data-chart-fallback]");
    var status = panel.querySelector("[data-chart-status]");
    var canvasWrap = panel.querySelector(".admin-chart-card__canvas-wrap");

    if (fallback) {
      fallback.hidden = !isUnavailable;
    }

    if (canvasWrap) {
      canvasWrap.hidden = isUnavailable;
    }

    if (status) {
      status.textContent = isUnavailable ? "Unavailable" : "Live";
      status.classList.toggle("is-unavailable", isUnavailable);
    }
  };

  var formatCurrency = function (value) {
    if (!isFiniteNumber(value)) {
      return "Unavailable";
    }

    return "NRs " + Math.round(Number(value)).toLocaleString("en-US");
  };

  var formatInteger = function (value) {
    if (!isFiniteNumber(value)) {
      return "Unavailable";
    }

    return Math.round(Number(value)).toLocaleString("en-US");
  };

  var formatPercent = function (value) {
    if (!isFiniteNumber(value)) {
      return "Unavailable";
    }

    return Number(value).toFixed(1) + "%";
  };

  var resolveTrend = function (trend) {
    if (!isFiniteNumber(trend)) {
      return {
        label: "New",
        className: "admin-kpi-card__trend--up",
      };
    }

    var trendValue = Number(trend);
    if (Math.abs(trendValue) < 0.05) {
      return {
        label: "0.0%",
        className: "admin-kpi-card__trend--flat",
      };
    }

    if (trendValue > 0) {
      return {
        label: "+" + trendValue.toFixed(1) + "%",
        className: "admin-kpi-card__trend--up",
      };
    }

    return {
      label: trendValue.toFixed(1) + "%",
      className: "admin-kpi-card__trend--down",
    };
  };

  var updateKpis = function (kpis) {
    var kpiNodes = document.querySelectorAll("[data-kpi-value]");
    for (var index = 0; index < kpiNodes.length; index += 1) {
      var valueNode = kpiNodes[index];
      var kpiKey = valueNode.getAttribute("data-kpi-value") || "";
      var formatType = (
        valueNode.getAttribute("data-kpi-format") || "integer"
      ).toLowerCase();
      var metric = kpis && typeof kpis === "object" ? kpis[kpiKey] : null;
      var metricValue =
        metric && typeof metric === "object" ? metric.value : null;

      if (formatType === "currency") {
        valueNode.textContent = formatCurrency(metricValue);
      } else if (formatType === "percent") {
        valueNode.textContent = formatPercent(metricValue);
      } else {
        valueNode.textContent = formatInteger(metricValue);
      }

      var trendNode = document.querySelector(
        '[data-kpi-trend="' + kpiKey + '"]',
      );
      if (!trendNode) {
        continue;
      }

      var trendInfo = resolveTrend(
        metric && typeof metric === "object" ? metric.trend : null,
      );
      trendNode.textContent = trendInfo.label;

      for (
        var trendIndex = 0;
        trendIndex < trendClassOptions.length;
        trendIndex += 1
      ) {
        trendNode.classList.remove(trendClassOptions[trendIndex]);
      }
      trendNode.classList.add(trendInfo.className);
    }

    var fleetTrendNode = document.querySelector(
      '[data-kpi-trend="fleetAvailability"]',
    );
    var fleetIconNode = document.querySelector(
      '[data-kpi-icon="fleetAvailability"]',
    );
    if (fleetTrendNode && fleetIconNode) {
      fleetIconNode.classList.remove(
        "admin-kpi-card__icon--up",
        "admin-kpi-card__icon--down",
      );
      if (fleetTrendNode.classList.contains("admin-kpi-card__trend--down")) {
        fleetIconNode.textContent = "trending_down";
        fleetIconNode.classList.add("admin-kpi-card__icon--down");
      } else {
        fleetIconNode.textContent = "trending_up";
        fleetIconNode.classList.add("admin-kpi-card__icon--up");
      }
    }
  };

  var getChartsApi = function () {
    if (!window.RidexCharts || typeof window.RidexCharts !== "object") {
      return null;
    }

    return window.RidexCharts;
  };

  var renderSalesChart = function (charts) {
    var chartsApi = getChartsApi();
    if (
      !chartsApi ||
      typeof chartsApi.renderSalesVehicleCategoryChart !== "function"
    ) {
      setUnavailableState("salesVehicleCategory", true);
      if (salesChartInstance) {
        salesChartInstance.destroy();
        salesChartInstance = null;
      }
      return;
    }

    salesChartInstance = chartsApi.renderSalesVehicleCategoryChart({
      charts: charts,
      currentChart: salesChartInstance,
      canvasId: "admin-sales-vehicle-category-chart",
      setUnavailableState: setUnavailableState,
    });
  };

  var renderMostRentedChart = function (charts) {
    var chartsApi = getChartsApi();
    if (
      !chartsApi ||
      typeof chartsApi.renderMostRentedFleetChart !== "function"
    ) {
      setUnavailableState("mostRentedVehicleCategory", true);
      if (rentChartInstance) {
        rentChartInstance.destroy();
        rentChartInstance = null;
      }
      return;
    }

    rentChartInstance = chartsApi.renderMostRentedFleetChart({
      charts: charts,
      currentChart: rentChartInstance,
      canvasId: "admin-most-rented-category-chart",
      setUnavailableState: setUnavailableState,
    });
  };

  var applyPayload = function (rawPayload) {
    var payload = normalizePayload(rawPayload);
    updateKpis(payload.kpis);
    renderSalesChart(payload.charts);
    renderMostRentedChart(payload.charts);
  };

  var fetchLatestPayload = function (refreshUrl) {
    if (
      refreshInFlight ||
      refreshUrl === "" ||
      typeof window.fetch !== "function"
    ) {
      return;
    }

    refreshInFlight = true;
    var abortController =
      typeof window.AbortController === "function"
        ? new window.AbortController()
        : null;
    var timeoutId = window.setTimeout(function () {
      if (abortController) {
        abortController.abort();
      }
    }, 10000);

    var finalize = function () {
      refreshInFlight = false;
      window.clearTimeout(timeoutId);
    };

    var requestOptions = {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
      headers: {
        Accept: "application/json",
      },
    };

    if (abortController) {
      requestOptions.signal = abortController.signal;
    }

    window
      .fetch(refreshUrl, requestOptions)
      .then(function (response) {
        if (!response.ok) {
          throw new Error(
            "Dashboard refresh failed with status " + response.status,
          );
        }
        return response.json();
      })
      .then(function (responseBody) {
        if (
          !responseBody ||
          responseBody.ok !== true ||
          typeof responseBody.payload !== "object"
        ) {
          throw new Error("Dashboard refresh returned an invalid payload.");
        }

        applyPayload(responseBody.payload);
      })
      .catch(function (error) {
        if (error && error.name === "AbortError") {
          return;
        }

        if (
          typeof console !== "undefined" &&
          typeof console.warn === "function"
        ) {
          console.warn("Admin dashboard live refresh failed:", error);
        }
      })
      .then(finalize, finalize);
  };

  var initialPayload = parseJsonText(dataNode.textContent || "{}");
  applyPayload(initialPayload);

  var refreshUrl = (dataNode.getAttribute("data-refresh-url") || "").trim();
  var refreshIntervalMs = Number(
    dataNode.getAttribute("data-refresh-interval-ms") || 30000,
  );
  if (!Number.isFinite(refreshIntervalMs) || refreshIntervalMs < 10000) {
    refreshIntervalMs = 30000;
  } else if (refreshIntervalMs > 300000) {
    refreshIntervalMs = 300000;
  }

  if (refreshUrl !== "") {
    fetchLatestPayload(refreshUrl);

    window.setInterval(function () {
      if (document.visibilityState === "hidden") {
        return;
      }

      fetchLatestPayload(refreshUrl);
    }, refreshIntervalMs);

    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "visible") {
        fetchLatestPayload(refreshUrl);
      }
    });
  }

  // duplicate sidebar init removed (handled earlier in file)
})();
