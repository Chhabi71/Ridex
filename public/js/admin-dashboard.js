/**
 * Purpose: Admin dashboard JS to load metrics and render charts/widgets.
 * Website Section: Admin Dashboard.
 * Developer Notes: Fetch metrics via AJAX, hydrate Chart.js configs, update KPI cards, and handle auto-refresh/filters.
 */

(function () {
	"use strict";

	var dataNode = document.getElementById("admin-dashboard-data");
	if (!dataNode) {
		return;
	}

	var parsedData = {};
	try {
		parsedData = JSON.parse(dataNode.textContent || "{}");
	} catch (error) {
		parsedData = {};
	}

	var hasNonZeroValues = function (values) {
		if (!Array.isArray(values)) {
			return false;
		}

		for (var i = 0; i < values.length; i += 1) {
			var numericValue = Number(values[i]);
			if (Number.isFinite(numericValue) && numericValue > 0) {
				return true;
			}
		}

		return false;
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

	var salesChartData = parsedData.salesVehicleCategory || {};
	var salesLabels = Array.isArray(salesChartData.labels) ? salesChartData.labels : [];
	var salesDatasets = Array.isArray(salesChartData.datasets) ? salesChartData.datasets : [];
	var salesHasData = false;

	for (var salesIndex = 0; salesIndex < salesDatasets.length; salesIndex += 1) {
		if (hasNonZeroValues(salesDatasets[salesIndex].data)) {
			salesHasData = true;
			break;
		}
	}

	if (typeof window.Chart !== "function" || !salesHasData || salesLabels.length === 0) {
		setUnavailableState("salesVehicleCategory", true);
	} else {
		setUnavailableState("salesVehicleCategory", false);
		var salesCanvas = document.getElementById("admin-sales-vehicle-category-chart");
		if (salesCanvas) {
			new window.Chart(salesCanvas, {
				type: "line",
				data: {
					labels: salesLabels,
					datasets: salesDatasets.map(function (dataset) {
						return {
							label: dataset.label || "",
							data: Array.isArray(dataset.data) ? dataset.data : [],
							borderColor: dataset.borderColor || "#4aa3ff",
							backgroundColor: dataset.borderColor || "#4aa3ff",
							pointRadius: 3,
							pointHoverRadius: 4,
							tension: 0,
							fill: false,
							borderWidth: 2,
						};
					}),
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						title: {
							display: true,
							text: "Chart.js Time Scale",
							font: {
								size: 11,
								weight: "600",
							},
							color: "#7d7d7d",
							padding: {
								bottom: 4,
							},
						},
						legend: {
							position: "top",
							labels: {
								boxWidth: 14,
								boxHeight: 8,
								usePointStyle: false,
								font: {
									size: 10,
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							title: {
								display: true,
								text: "value",
								font: {
									size: 10,
								},
							},
							ticks: {
								precision: 0,
								font: {
									size: 10,
								},
							},
							grid: {
								color: "rgba(11, 11, 11, 0.08)",
							},
						},
						x: {
							title: {
								display: true,
								text: "Date",
								font: {
									size: 10,
								},
							},
							ticks: {
								font: {
									size: 9,
								},
							},
							grid: {
								display: false,
							},
						},
					},
				},
			});
		}
	}

	var rentChartData = parsedData.mostRentedVehicleCategory || {};
	var rentLabels = Array.isArray(rentChartData.labels) ? rentChartData.labels : [];
	var rentDatasets = Array.isArray(rentChartData.datasets) ? rentChartData.datasets : [];
	var rentValues = rentDatasets[0] && Array.isArray(rentDatasets[0].data) ? rentDatasets[0].data : [];
	var rentHasData = hasNonZeroValues(rentValues);

	if (typeof window.Chart !== "function" || !rentHasData || rentLabels.length === 0) {
		setUnavailableState("mostRentedVehicleCategory", true);
		return;
	}

	setUnavailableState("mostRentedVehicleCategory", false);
	var rentCanvas = document.getElementById("admin-most-rented-category-chart");
	if (!rentCanvas) {
		return;
	}

	new window.Chart(rentCanvas, {
		type: "doughnut",
		data: {
			labels: rentLabels,
			datasets: [
				{
					data: rentValues,
					backgroundColor: Array.isArray(rentDatasets[0].backgroundColor)
						? rentDatasets[0].backgroundColor
						: ["#f75b7a", "#f6a340", "#f4ca55"],
					borderWidth: 0,
				},
			],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			cutout: "62%",
			plugins: {
				title: {
					display: true,
					text: "Chart.js Pie Chart",
					font: {
						size: 8,
						weight: "400",
					},
					color: "#9b9b9b",
				},
				legend: {
					position: "top",
					labels: {
						boxWidth: 10,
						boxHeight: 10,
						padding: 10,
						font: {
							size: 8,
						},
					},
				},
			},
		},
	});
})();
