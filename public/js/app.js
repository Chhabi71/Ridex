/**
 * Purpose: Shared frontend behaviors (nav interactions, modals, alerts, common utilities).
 * Website Section: Global Frontend.
 * Developer Notes: Implement nav toggle, flash auto-dismiss, CSRF header setup for AJAX, and shared helper functions.
 */

(function () {
  // admin login: stack-based modal navigation for menu/admin auth flow
  const modals = Array.from(
    document.querySelectorAll(".menu-modal[data-modal-id]"),
  );
  if (modals.length === 0) {
    return;
  }

  const modalById = new Map();
  modals.forEach((modal) => {
    if (modal.id) {
      modalById.set(modal.id, modal);
    }
  });

  const menuOpenButtons = Array.from(
    document.querySelectorAll("[data-menu-open]"),
  );
  const closeButtons = Array.from(
    document.querySelectorAll("[data-modal-close]"),
  );
  const backButtons = Array.from(
    document.querySelectorAll("[data-modal-back]"),
  );
  const targetButtons = Array.from(
    document.querySelectorAll("[data-modal-target]"),
  );
  const navigationLinks = Array.from(
    document.querySelectorAll(".menu-modal__link[href]"),
  );
  const passwordToggleButtons = Array.from(
    document.querySelectorAll("[data-password-toggle]"),
  );
  const adminLoginModal = document.getElementById("admin-login-modal");
  const adminVehicleReadModal = document.getElementById(
    "admin-vehicle-read-modal",
  );
  const adminMaintenanceFillModal = document.getElementById(
    "admin-maintenance-fill-modal",
  );
  const adminMaintenanceEditModal = document.getElementById(
    "admin-maintenance-edit-modal",
  );
  const adminCreateVehicleModal = document.getElementById(
    "admin-create-vehicle-modal",
  );
  const adminEditVehicleModal = document.getElementById(
    "admin-edit-vehicle-modal",
  );
  const adminDeleteVehicleModal = document.getElementById(
    "admin-delete-vehicle-modal",
  );
  const adminBookingReadModal = document.getElementById(
    "admin-booking-read-modal",
  );
  const adminBookingTrackModal = document.getElementById(
    "admin-booking-track-modal",
  );
  const adminDeleteBookingModal = document.getElementById(
    "admin-delete-booking-modal",
  );
  const adminDeleteVehicleIdInput = adminDeleteVehicleModal?.querySelector(
    "[data-delete-vehicle-id-input]",
  );
  const adminDeleteVehicleNameNode = adminDeleteVehicleModal?.querySelector(
    "[data-delete-vehicle-name]",
  );
  const adminDeleteFleetModeInput = adminDeleteVehicleModal?.querySelector(
    "[data-delete-fleet-mode-input]",
  );
  const adminDeleteFleetTypeInput = adminDeleteVehicleModal?.querySelector(
    "[data-delete-fleet-type-input]",
  );
  const adminDeleteFleetStatusInput = adminDeleteVehicleModal?.querySelector(
    "[data-delete-fleet-status-input]",
  );
  const adminBookingReadNumberNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-number]",
  );
  const adminBookingReadCustomerNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-customer]",
  );
  const adminBookingReadPaymentBadgeNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-payment-badge]",
  );
  const adminBookingReadPaymentIconNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-payment-icon]",
  );
  const adminBookingReadPaymentLabelNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-payment-label]",
  );
  const adminBookingReadStatusPill = adminBookingReadModal?.querySelector(
    "[data-booking-read-status-pill]",
  );
  const adminBookingReadImage = adminBookingReadModal?.querySelector(
    "[data-booking-read-image]",
  );
  const adminBookingReadVehicleTypeNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-vehicle-type]",
  );
  const adminBookingReadVehicleNameNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-vehicle-name]",
  );
  const adminBookingReadCustomerPhoneNode =
    adminBookingReadModal?.querySelector("[data-booking-read-customer-phone]");
  const adminBookingReadCustomerEmailNode =
    adminBookingReadModal?.querySelector("[data-booking-read-customer-email]");
  const adminBookingReadDriverIdNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-driver-id]",
  );
  const adminBookingReadPickupDateNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-pickup-date]",
  );
  const adminBookingReadReturnDateNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-return-date]",
  );
  const adminBookingReadReturnTimeNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-return-time]",
  );
  const adminBookingReadPricePerDayNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-price-per-day]",
  );
  const adminBookingReadDurationLabelNode =
    adminBookingReadModal?.querySelector("[data-booking-read-duration-label]");
  const adminBookingReadDurationPriceNode =
    adminBookingReadModal?.querySelector("[data-booking-read-duration-price]");
  const adminBookingReadDropChargeNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-drop-charge]",
  );
  const adminBookingReadLateFeeNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-late-fee]",
  );
  const adminBookingReadTaxesFeesNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-taxes-fees]",
  );
  const adminBookingReadBillingTotalNode = adminBookingReadModal?.querySelector(
    "[data-booking-read-billing-total]",
  );
  const adminBookingCompleteForm = adminBookingReadModal?.querySelector(
    "[data-booking-complete-form]",
  );
  const adminBookingCompleteIdInput = adminBookingReadModal?.querySelector(
    "[data-booking-complete-id-input]",
  );
  const adminBookingReturnTimeInput = adminBookingReadModal?.querySelector(
    "[data-booking-return-time-input]",
  );
  const adminBookingReturnTimePickerButtons = adminBookingReadModal
    ? Array.from(
        adminBookingReadModal.querySelectorAll(
          "[data-open-picker-for='admin-booking-return-time-input']",
        ),
      )
    : [];
  const adminBookingLateFeePreview = adminBookingReadModal?.querySelector(
    "[data-booking-late-fee-preview]",
  );
  const adminBookingCompleteSubmit = adminBookingReadModal?.querySelector(
    "[data-booking-complete-submit]",
  );
  const adminBookingTrackAction = adminBookingReadModal?.querySelector(
    "[data-booking-track-action]",
  );
  const adminBookingApproveForm = adminBookingReadModal?.querySelector(
    "[data-booking-approve-form]",
  );
  const adminBookingApproveIdInput = adminBookingReadModal?.querySelector(
    "[data-booking-approve-id-input]",
  );
  const adminBookingApproveAction = adminBookingReadModal?.querySelector(
    "[data-booking-approve-action]",
  );
  const adminBookingDeleteAction = adminBookingReadModal?.querySelector(
    "[data-booking-delete-action]",
  );
  const adminBookingTrackNumberNode = adminBookingTrackModal?.querySelector(
    "[data-booking-track-number]",
  );
  const adminBookingTrackCustomerNode = adminBookingTrackModal?.querySelector(
    "[data-booking-track-customer]",
  );
  const adminBookingTrackLocationLabelNode =
    adminBookingTrackModal?.querySelector(
      "[data-booking-track-location-label]",
    );
  const adminBookingTrackMapEmptyNode = adminBookingTrackModal?.querySelector(
    "[data-booking-track-map-empty]",
  );
  const adminBookingTrackRouteNode = adminBookingTrackModal?.querySelector(
    "[data-booking-track-route]",
  );
  const adminBookingTrackPickupPinNode = adminBookingTrackModal?.querySelector(
    "[data-booking-track-pickup-pin]",
  );
  const adminBookingTrackReturnPinNode = adminBookingTrackModal?.querySelector(
    "[data-booking-track-return-pin]",
  );
  const adminBookingTrackOpenMapsAction = adminBookingTrackModal?.querySelector(
    "[data-booking-track-open-maps]",
  );
  const adminBookingTrackPickupLocationNode =
    adminBookingTrackModal?.querySelector(
      "[data-booking-track-pickup-location]",
    );
  const adminBookingTrackReturnLocationNode =
    adminBookingTrackModal?.querySelector(
      "[data-booking-track-return-location]",
    );
  // risk/safety nodes removed
  const adminDeleteBookingIdInput = adminDeleteBookingModal?.querySelector(
    "[data-delete-booking-id-input]",
  );
  const adminDeleteBookingNameNode = adminDeleteBookingModal?.querySelector(
    "[data-delete-booking-name]",
  );
  const adminReadBookingNumberNode = adminVehicleReadModal?.querySelector(
    "[data-read-booking-number]",
  );
  const adminReadCurrentUserLine = adminVehicleReadModal?.querySelector(
    "[data-read-current-user-line]",
  );
  const adminReadCurrentUserNode = adminVehicleReadModal?.querySelector(
    "[data-read-current-user]",
  );
  const adminReadStatusPill = adminVehicleReadModal?.querySelector(
    "[data-read-status-pill]",
  );
  const adminReadMaintenanceIndicator = adminVehicleReadModal?.querySelector(
    "[data-read-maintenance-indicator]",
  );
  const adminReadVehicleImage = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-image]",
  );
  const adminReadVehicleTypeNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-type]",
  );
  const adminReadVehicleFullNameNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-full-name]",
  );
  const adminReadVehicleSeatsNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-seats]",
  );
  const adminReadVehicleTransmissionNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-transmission]",
  );
  const adminReadVehicleAgeNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-age]",
  );
  const adminReadVehicleFuelNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-fuel]",
  );
  const adminReadVehicleMileageNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-mileage]",
  );
  const adminReadVehiclePlateNode = adminVehicleReadModal?.querySelector(
    "[data-read-vehicle-plate]",
  );
  const adminReadStatusRowsNode = adminVehicleReadModal?.querySelector(
    "[data-read-status-rows]",
  );
  const adminReadDeleteAction = adminVehicleReadModal?.querySelector(
    "[data-read-delete-action]",
  );
  const adminReadEditAction = adminVehicleReadModal?.querySelector(
    "[data-read-edit-action]",
  );
  const adminMaintenanceFillForm = adminMaintenanceFillModal?.querySelector(
    "[data-maintenance-fill-form]",
  );
  const adminMaintenanceFillVehicleIdInput =
    adminMaintenanceFillModal?.querySelector(
      "[data-maintenance-vehicle-id-input]",
    );
  const adminMaintenanceFillFleetModeInput =
    adminMaintenanceFillModal?.querySelector(
      "[data-maintenance-fleet-mode-input]",
    );
  const adminMaintenanceFillFleetTypeInput =
    adminMaintenanceFillModal?.querySelector(
      "[data-maintenance-fleet-type-input]",
    );
  const adminMaintenanceFillFleetStatusInput =
    adminMaintenanceFillModal?.querySelector(
      "[data-maintenance-fleet-status-input]",
    );
  const adminMaintenanceFillIssueInput =
    adminMaintenanceFillModal?.querySelector("[data-maintenance-issue-input]");
  const adminMaintenanceFillWorkshopInput =
    adminMaintenanceFillModal?.querySelector(
      "[data-maintenance-workshop-input]",
    );
  const adminMaintenanceFillEstimateInput =
    adminMaintenanceFillModal?.querySelector(
      "[data-maintenance-estimate-input]",
    );
  const adminMaintenanceFillCostInput =
    adminMaintenanceFillModal?.querySelector("[data-maintenance-cost-input]");
  const adminMaintenanceEditForm = adminMaintenanceEditModal?.querySelector(
    "[data-maintenance-edit-form]",
  );
  const adminMaintenanceEditVehicleIdInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-vehicle-id-input]",
    );
  const adminMaintenanceEditFleetModeInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-fleet-mode-input]",
    );
  const adminMaintenanceEditFleetTypeInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-fleet-type-input]",
    );
  const adminMaintenanceEditFleetStatusInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-fleet-status-input]",
    );
  const adminMaintenanceEditIssueInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-issue-input]",
    );
  const adminMaintenanceEditWorkshopInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-workshop-input]",
    );
  const adminMaintenanceEditEstimateInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-estimate-input]",
    );
  const adminMaintenanceEditCostInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-edit-cost-input]",
    );
  const adminMaintenanceCompleteVehicleIdInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-complete-vehicle-id-input]",
    );
  const adminMaintenanceCompleteFleetModeInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-complete-fleet-mode-input]",
    );
  const adminMaintenanceCompleteFleetTypeInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-complete-fleet-type-input]",
    );
  const adminMaintenanceCompleteFleetStatusInput =
    adminMaintenanceEditModal?.querySelector(
      "[data-maintenance-complete-fleet-status-input]",
    );
  const adminCreateVehicleForm = adminCreateVehicleModal?.querySelector(
    "[data-admin-create-vehicle-form]",
  );
  const adminCreateStepLabel = adminCreateVehicleModal?.querySelector(
    "[data-create-step-label]",
  );
  const adminCreateStepPanels = adminCreateVehicleModal
    ? Array.from(
        adminCreateVehicleModal.querySelectorAll("[data-create-step-panel]"),
      )
    : [];
  const adminCreateStepNextButtons = adminCreateVehicleModal
    ? Array.from(
        adminCreateVehicleModal.querySelectorAll("[data-create-step-next]"),
      )
    : [];
  const adminCreateStepPrevButtons = adminCreateVehicleModal
    ? Array.from(
        adminCreateVehicleModal.querySelectorAll("[data-create-step-prev]"),
      )
    : [];
  const adminCreateStepCloseButton = adminCreateVehicleModal?.querySelector(
    "[data-create-step-close]",
  );
  const adminCreateFleetModeInput = adminCreateVehicleModal?.querySelector(
    "[data-create-fleet-mode-input]",
  );
  const adminCreateFleetTypeInput = adminCreateVehicleModal?.querySelector(
    "[data-create-fleet-type-input]",
  );
  const adminCreateVehicleTypeInput = adminCreateVehicleModal?.querySelector(
    "[data-create-vehicle-type-input]",
  );
  const adminCreateImageFieldsRoot = adminCreateVehicleModal?.querySelector(
    "[data-create-image-fields]",
  );
  const adminCreateLastServiceInput = adminCreateVehicleModal?.querySelector(
    "[data-create-last-service-input]",
  );
  const adminEditVehicleForm = adminEditVehicleModal?.querySelector(
    "[data-admin-edit-vehicle-form]",
  );
  const adminEditStepPanels = adminEditVehicleModal
    ? Array.from(
        adminEditVehicleModal.querySelectorAll("[data-edit-step-panel]"),
      )
    : [];
  const adminEditStepNextButtons = adminEditVehicleModal
    ? Array.from(
        adminEditVehicleModal.querySelectorAll("[data-edit-step-next]"),
      )
    : [];
  const adminEditStepPrevButtons = adminEditVehicleModal
    ? Array.from(
        adminEditVehicleModal.querySelectorAll("[data-edit-step-prev]"),
      )
    : [];
  const adminEditStepCloseButton = adminEditVehicleModal?.querySelector(
    "[data-edit-step-close]",
  );
  const adminEditVehicleIdInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-id-input]",
  );
  const adminEditCurrentImagePathInput = adminEditVehicleModal?.querySelector(
    "[data-edit-current-image-path-input]",
  );
  const adminEditRemoveCurrentImageInput = adminEditVehicleModal?.querySelector(
    "[data-edit-remove-current-image-input]",
  );
  const adminEditReplacePrimaryImageInput =
    adminEditVehicleModal?.querySelector(
      "[data-edit-replace-primary-image-input]",
    );
  const adminEditFleetModeInput = adminEditVehicleModal?.querySelector(
    "[data-edit-fleet-mode-input]",
  );
  const adminEditFleetTypeInput = adminEditVehicleModal?.querySelector(
    "[data-edit-fleet-type-input]",
  );
  const adminEditFleetStatusInput = adminEditVehicleModal?.querySelector(
    "[data-edit-fleet-status-input]",
  );
  const adminEditVehicleTypeInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-type-input]",
  );
  const adminEditFullNameInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-full-name-input]",
  );
  const adminEditShortNameInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-short-name-input]",
  );
  const adminEditPriceInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-price-input]",
  );
  const adminEditDriverAgeInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-driver-age-input]",
  );
  const adminEditSeatsInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-seats-input]",
  );
  const adminEditTransmissionInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-transmission-input]",
  );
  const adminEditFuelInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-fuel-input]",
  );
  const adminEditMileageInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-mileage-input]",
  );
  const adminEditLicenseInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-license-input]",
  );
  const adminEditGpsInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-gps-id-input]",
  );
  const adminEditStatusInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-status-input]",
  );
  const adminEditLastServiceInput = adminEditVehicleModal?.querySelector(
    "[data-edit-last-service-input]",
  );
  const adminEditDescriptionInput = adminEditVehicleModal?.querySelector(
    "[data-edit-vehicle-description-input]",
  );
  const adminEditImageFieldsRoot = adminEditVehicleModal?.querySelector(
    "[data-edit-image-fields]",
  );
  const adminCreateFieldPool = adminCreateVehicleModal?.querySelector(
    "[data-create-field-pool]",
  );
  const adminCreateFieldBlocks = adminCreateFieldPool
    ? Array.from(adminCreateFieldPool.querySelectorAll("[data-field-block]"))
    : [];
  const adminCreateFieldZone2 = adminCreateVehicleModal?.querySelector(
    "[data-create-field-zone='2']",
  );
  const adminCreateFieldZone3 = adminCreateVehicleModal?.querySelector(
    "[data-create-field-zone='3']",
  );
  const adminCreateFieldZone4 = adminCreateVehicleModal?.querySelector(
    "[data-create-field-zone='4']",
  );
  const adminCreateStep3NextButton = adminCreateVehicleModal?.querySelector(
    "[data-create-step3-next]",
  );
  const adminCreateStep3SubmitButton = adminCreateVehicleModal?.querySelector(
    "[data-create-step3-submit]",
  );
  const adminCreateStep4SubmitButton = adminCreateVehicleModal?.querySelector(
    "[data-create-step4-submit]",
  );
  const adminCreateStep4Panel = adminCreateVehicleModal?.querySelector(
    "[data-create-step-panel='4']",
  );
  const adminEditFieldPool = adminEditVehicleModal?.querySelector(
    "[data-edit-field-pool]",
  );
  const adminEditFieldBlocks = adminEditFieldPool
    ? Array.from(adminEditFieldPool.querySelectorAll("[data-field-block]"))
    : [];
  const adminEditFieldZone2 = adminEditVehicleModal?.querySelector(
    "[data-edit-field-zone='2']",
  );
  const adminEditFieldZone3 = adminEditVehicleModal?.querySelector(
    "[data-edit-field-zone='3']",
  );
  const adminEditFieldZone4 = adminEditVehicleModal?.querySelector(
    "[data-edit-field-zone='4']",
  );
  const adminEditStep3NextButton = adminEditVehicleModal?.querySelector(
    "[data-edit-step3-next]",
  );
  const adminEditStep3SubmitButton = adminEditVehicleModal?.querySelector(
    "[data-edit-step3-submit]",
  );
  const adminEditStep4SubmitButton = adminEditVehicleModal?.querySelector(
    "[data-edit-step4-submit]",
  );
  const adminEditStep4Panel = adminEditVehicleModal?.querySelector(
    "[data-edit-step-panel='4']",
  );
  const adminCreateSelectInputs = Array.from(
    document.querySelectorAll(".admin-create-vehicle-modal__select"),
  );
  const adminLoginForm = adminLoginModal?.querySelector(
    "[data-admin-login-form]",
  );
  const adminLoginInputs = adminLoginModal
    ? Array.from(adminLoginModal.querySelectorAll("[data-admin-login-input]"))
    : [];
  const adminPasswordInput = document.getElementById("admin-password-input");
  const adminPasswordToggle = adminLoginModal?.querySelector(
    "[data-password-toggle][data-password-target='admin-password-input']",
  );

  let lastFocusedElement = null;
  const modalHistory = [];
  let readModalContext = null;
  let createVehicleStep = 1;
  let editVehicleStep = 1;
  const maxFieldsPerPage = 5;
  let createStep4Enabled = false;
  let editStep4Enabled = false;
  const createCustomSelectControllers = [];
  let openCreateCustomSelectController = null;

  const clearAdminLoginUiState = () => {
    if (adminLoginForm instanceof HTMLFormElement) {
      adminLoginForm.reset();
    }

    adminLoginInputs.forEach((input) => {
      if (input instanceof HTMLElement) {
        input.classList.remove("admin-login-form__input--error");
      }
    });

    const loginError = adminLoginModal?.querySelector(
      "[data-admin-login-error]",
    );
    if (loginError instanceof HTMLElement) {
      loginError.remove();
    }

    if (adminPasswordInput instanceof HTMLInputElement) {
      adminPasswordInput.type = "password";
    }

    if (adminPasswordToggle instanceof HTMLElement) {
      adminPasswordToggle.setAttribute("aria-label", "Show password");
      const icon = adminPasswordToggle.querySelector(
        ".material-symbols-rounded",
      );
      if (icon) {
        icon.textContent = "visibility";
      }
    }
  };

  // read modal reference: C:\Users\User\Downloads\Ridex includes the 5-status read examples, and available status has an extra maintenance icon.
  const readStatusLabels = {
    available: "Available",
    reserved: "Reserved",
    on_trip: "On Trip",
    overdue: "Overdue",
    maintenance: "Maintenance",
  };

  const readUnavailableText = "unavailable";
  const readUserNotApplicableText = "N/A";
  const readUserStatuses = ["reserved", "on_trip", "overdue"];

  const toTitleCase = (rawValue) =>
    String(rawValue || "")
      .replace(/[_-]+/g, " ")
      .replace(/\b\w/g, (character) => character.toUpperCase());

  const isReadValueMissing = (rawValue) =>
    rawValue === null ||
    rawValue === undefined ||
    String(rawValue).trim() === "";

  const getReadMissingFallback = (statusKey, isUserRelated = false) => {
    if (
      isUserRelated &&
      (statusKey === "available" || statusKey === "maintenance")
    ) {
      return readUserNotApplicableText;
    }

    return readUnavailableText;
  };

  const normalizeReadValue = (rawValue, statusKey, isUserRelated = false) => {
    if (isReadValueMissing(rawValue)) {
      return getReadMissingFallback(statusKey, isUserRelated);
    }

    return String(rawValue).trim();
  };

  const formatReadDate = (rawValue, statusKey, isUserRelated = false) => {
    if (isReadValueMissing(rawValue)) {
      return getReadMissingFallback(statusKey, isUserRelated);
    }

    const text = String(rawValue).trim();

    const parsedDate = new Date(text.replace(" ", "T"));
    if (Number.isNaN(parsedDate.getTime())) {
      return getReadMissingFallback(statusKey, isUserRelated);
    }

    const day = String(parsedDate.getDate()).padStart(2, "0");
    const month = parsedDate.toLocaleString("en-US", { month: "short" });
    const year = parsedDate.getFullYear();

    return `${day} ${month}, ${year}`;
  };

  const formatReadCurrency = (rawValue, statusKey, isUserRelated = false) => {
    if (isReadValueMissing(rawValue)) {
      return getReadMissingFallback(statusKey, isUserRelated);
    }

    const numericValue = Number.parseFloat(String(rawValue).trim());
    if (!Number.isFinite(numericValue)) {
      return getReadMissingFallback(statusKey, isUserRelated);
    }

    return `NRs ${numericValue.toFixed(2)}`;
  };

  const escapeHtml = (rawValue) =>
    String(rawValue || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const buildReadStatusRows = (statusKey, details) => {
    const rows = [];
    const formattedLastServiceDate = formatReadDate(
      details.lastServiceDate,
      statusKey,
      false,
    );

    if (statusKey === "available") {
      rows.push(["Last Service Date", formattedLastServiceDate]);
      rows.push([
        "Upcoming Reservations",
        formatReadDate(details.upcomingPickupDatetime, statusKey, false),
      ]);
      rows.push([
        "Total Earnings",
        formatReadCurrency(details.totalEarnings, statusKey, false),
      ]);
      rows.push([
        "Total Reservations",
        normalizeReadValue(details.totalReservations, statusKey, false),
      ]);
      return rows;
    }

    if (statusKey === "reserved") {
      const reservedPaymentStatus = normalizeReadValue(
        details.paymentStatus,
        statusKey,
        false,
      );
      rows.push(["Last Service Date", formattedLastServiceDate]);
      rows.push([
        "Pickup Date",
        formatReadDate(details.pickupDatetime, statusKey, false),
      ]);
      rows.push([
        "Return Date",
        formatReadDate(details.returnDatetime, statusKey, false),
      ]);
      rows.push([
        "Payment Status",
        reservedPaymentStatus === readUnavailableText
          ? readUnavailableText
          : toTitleCase(reservedPaymentStatus),
      ]);
      return rows;
    }

    if (statusKey === "on_trip") {
      const onTripPaymentStatus = normalizeReadValue(
        details.paymentStatus,
        statusKey,
        false,
      );
      rows.push(["Last Service Date", formattedLastServiceDate]);
      rows.push([
        "Pickup Date",
        formatReadDate(details.pickupDatetime, statusKey, false),
      ]);
      rows.push([
        "Return Date",
        formatReadDate(details.returnDatetime, statusKey, false),
      ]);
      rows.push([
        "Current Location",
        normalizeReadValue(details.currentLocation, statusKey, false),
      ]);
      rows.push([
        "Payment Status",
        onTripPaymentStatus === readUnavailableText
          ? readUnavailableText
          : toTitleCase(onTripPaymentStatus),
      ]);
      return rows;
    }

    if (statusKey === "overdue") {
      const overduePaymentStatus = normalizeReadValue(
        details.paymentStatus,
        statusKey,
        false,
      );
      let overdueDateLabel = formatReadDate(
        details.returnDatetime,
        statusKey,
        false,
      );
      const parsedReturnDatetime = new Date(
        String(details.returnDatetime || "").replace(" ", "T"),
      );
      if (
        overdueDateLabel !== readUnavailableText &&
        overdueDateLabel !== readUserNotApplicableText &&
        !Number.isNaN(parsedReturnDatetime.getTime())
      ) {
        const nowTimestamp = Date.now();
        const overdueHours = Math.floor(
          (nowTimestamp - parsedReturnDatetime.getTime()) / (1000 * 60 * 60),
        );
        if (overdueHours > 0) {
          overdueDateLabel = `${escapeHtml(overdueDateLabel)} <span class="admin-vehicle-read-modal__overdue-hours">+${overdueHours} hours</span>`;
        } else {
          overdueDateLabel = escapeHtml(overdueDateLabel);
        }
      } else {
        overdueDateLabel = escapeHtml(overdueDateLabel);
      }

      rows.push(["Last Service Date", formattedLastServiceDate]);
      rows.push(["Return Date", overdueDateLabel, true]);
      rows.push([
        "Current Location",
        normalizeReadValue(details.currentLocation, statusKey, false),
      ]);
      rows.push([
        "Total Late Fee (NRs 10/h)",
        formatReadCurrency(details.lateFee, statusKey, false),
      ]);
      rows.push([
        "Payment Status",
        overduePaymentStatus === readUnavailableText
          ? readUnavailableText
          : toTitleCase(overduePaymentStatus),
      ]);
      return rows;
    }

    rows.push([
      "Issue Description",
      normalizeReadValue(details.maintenanceIssueDescription, statusKey, false),
    ]);
    rows.push([
      "Workshop Name",
      normalizeReadValue(details.maintenanceWorkshop, statusKey, false),
    ]);
    rows.push([
      "Est. Completion",
      formatReadDate(
        details.maintenanceEstimateDatetime ||
          details.upcomingPickupDatetime ||
          details.returnDatetime,
        statusKey,
        false,
      ),
    ]);
    rows.push([
      "Service Cost",
      formatReadCurrency(details.maintenanceCost, statusKey, false),
    ]);
    return rows;
  };

  const updateReadStatusRows = (statusKey, details) => {
    if (!(adminReadStatusRowsNode instanceof HTMLElement)) {
      return;
    }

    const rows = buildReadStatusRows(statusKey, details);
    const rowMarkup = rows
      .map(([label, value, isHtml]) => {
        const safeLabel = escapeHtml(label || "");
        const safeValue = String(value || "N/A");
        return `
					<tr>
						<th scope="row">${safeLabel}</th>
						<td>${isHtml ? safeValue : escapeHtml(safeValue)}</td>
					</tr>
				`;
      })
      .join("");

    adminReadStatusRowsNode.innerHTML = rowMarkup;
  };

  // maintenance edit/fillup form: normalize YYYY-MM-DD date values for date inputs.
  const toDateInputValue = (rawValue) => {
    if (isReadValueMissing(rawValue)) {
      return "";
    }

    const normalized = String(rawValue).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
      return normalized;
    }

    const parsedDate = new Date(normalized.replace(" ", "T"));
    if (Number.isNaN(parsedDate.getTime())) {
      return "";
    }

    const day = String(parsedDate.getDate()).padStart(2, "0");
    const month = parsedDate.toLocaleString("en-US", { month: "short" });
    const year = parsedDate.getFullYear();
    return `${day} ${month}, ${year}`;
  };

  const parseDateOnlyValue = (rawValue) => {
    const normalized = String(rawValue || "").trim();
    if (normalized === "") {
      return null;
    }

    const ymdMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(normalized);
    if (ymdMatch) {
      const year = Number.parseInt(ymdMatch[1], 10);
      const month = Number.parseInt(ymdMatch[2], 10);
      const day = Number.parseInt(ymdMatch[3], 10);
      const date = new Date(year, month - 1, day, 0, 0, 0, 0);
      if (
        !Number.isNaN(date.getTime()) &&
        date.getFullYear() === year &&
        date.getMonth() === month - 1 &&
        date.getDate() === day
      ) {
        return date;
      }
      return null;
    }

    const dmyNumericMatch = /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/.exec(
      normalized,
    );
    if (dmyNumericMatch) {
      const day = Number.parseInt(dmyNumericMatch[1], 10);
      const month = Number.parseInt(dmyNumericMatch[2], 10);
      const year = Number.parseInt(dmyNumericMatch[3], 10);
      const date = new Date(year, month - 1, day, 0, 0, 0, 0);
      if (
        !Number.isNaN(date.getTime()) &&
        date.getFullYear() === year &&
        date.getMonth() === month - 1 &&
        date.getDate() === day
      ) {
        return date;
      }
      return null;
    }

    const dMonYMatch = /^(\d{1,2})\s+([A-Za-z]{3,12}),?\s+(\d{4})$/.exec(
      normalized,
    );
    if (dMonYMatch) {
      const fallbackDate = new Date(
        `${dMonYMatch[1]} ${dMonYMatch[2]} ${dMonYMatch[3]}`,
      );
      if (!Number.isNaN(fallbackDate.getTime())) {
        fallbackDate.setHours(0, 0, 0, 0);
        return fallbackDate;
      }
      return null;
    }

    const fallbackDate = new Date(normalized.replace(" ", "T"));
    if (Number.isNaN(fallbackDate.getTime())) {
      return null;
    }

    fallbackDate.setHours(0, 0, 0, 0);
    return fallbackDate;
  };

  const getTodayDateOnly = () => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today;
  };

  const validateDateNotInFuture = (
    inputNode,
    invalidDateMessage,
    futureDateMessage,
    shouldReport = true,
  ) => {
    if (!(inputNode instanceof HTMLInputElement)) {
      return true;
    }

    inputNode.setCustomValidity("");
    const parsedDate = parseDateOnlyValue(inputNode.value);
    if (!(parsedDate instanceof Date)) {
      inputNode.setCustomValidity(invalidDateMessage);
      if (shouldReport) {
        inputNode.reportValidity();
      }
      return false;
    }

    if (parsedDate > getTodayDateOnly()) {
      inputNode.setCustomValidity(futureDateMessage);
      if (shouldReport) {
        inputNode.reportValidity();
      }
      return false;
    }

    return true;
  };

  const validateDateNotBeforeToday = (
    inputNode,
    invalidDateMessage,
    pastDateMessage,
    shouldReport = true,
  ) => {
    if (!(inputNode instanceof HTMLInputElement)) {
      return true;
    }

    inputNode.setCustomValidity("");
    const parsedDate = parseDateOnlyValue(inputNode.value);
    if (!(parsedDate instanceof Date)) {
      inputNode.setCustomValidity(invalidDateMessage);
      if (shouldReport) {
        inputNode.reportValidity();
      }
      return false;
    }

    if (parsedDate < getTodayDateOnly()) {
      inputNode.setCustomValidity(pastDateMessage);
      if (shouldReport) {
        inputNode.reportValidity();
      }
      return false;
    }

    return true;
  };

  const getBookingModalsApi = () => {
    if (
      !window.RidexBookingModals ||
      typeof window.RidexBookingModals !== "object"
    ) {
      return null;
    }

    return window.RidexBookingModals;
  };

  const initAdminBookingReturnTimePicker = () => {
    const bookingModalsApi = getBookingModalsApi();
    if (
      !bookingModalsApi ||
      typeof bookingModalsApi.initAdminBookingReturnTimePicker !== "function"
    ) {
      return;
    }

    bookingModalsApi.initAdminBookingReturnTimePicker();
  };

  const resetBookingReadModal = () => {
    const bookingModalsApi = getBookingModalsApi();
    if (
      !bookingModalsApi ||
      typeof bookingModalsApi.resetBookingReadModal !== "function"
    ) {
      return;
    }

    bookingModalsApi.resetBookingReadModal();
  };

  const updateBookingLateFeePreview = () => {
    const bookingModalsApi = getBookingModalsApi();
    if (
      !bookingModalsApi ||
      typeof bookingModalsApi.updateBookingLateFeePreview !== "function"
    ) {
      return;
    }

    bookingModalsApi.updateBookingLateFeePreview();
  };

  const validateAdminBookingReturnTime = (shouldReport = false) => {
    const bookingModalsApi = getBookingModalsApi();
    if (
      !bookingModalsApi ||
      typeof bookingModalsApi.validateAdminReturnTimeInput !== "function"
    ) {
      return true;
    }

    return bookingModalsApi.validateAdminReturnTimeInput(shouldReport);
  };

  const hydrateBookingReadModal = (triggerButton) => {
    const bookingModalsApi = getBookingModalsApi();
    if (
      !bookingModalsApi ||
      typeof bookingModalsApi.hydrateBookingReadModal !== "function"
    ) {
      return;
    }

    bookingModalsApi.hydrateBookingReadModal(triggerButton);
  };

  const hydrateBookingTrackModal = (triggerButton) => {
    const bookingModalsApi = getBookingModalsApi();
    if (
      !bookingModalsApi ||
      typeof bookingModalsApi.hydrateBookingTrackModal !== "function"
    ) {
      return;
    }

    bookingModalsApi.hydrateBookingTrackModal(triggerButton);
  };

  const hydrateDeleteBookingModal = (triggerButton) => {
    const bookingModalsApi = getBookingModalsApi();
    if (
      !bookingModalsApi ||
      typeof bookingModalsApi.hydrateDeleteBookingModal !== "function"
    ) {
      return;
    }

    bookingModalsApi.hydrateDeleteBookingModal(triggerButton);
  };

  // maintenance edit/fillup form: populate create modal with currently selected available vehicle context.
  const resolveMaintenanceContext = (triggerButton = null) => {
    if (triggerButton instanceof HTMLElement) {
      const triggerVehicleId = Number.parseInt(
        triggerButton.getAttribute("data-read-vehicle-id") || "0",
        10,
      );
      const triggerIssue =
        triggerButton.getAttribute("data-read-maintenance-issue") || "";
      const triggerWorkshop =
        triggerButton.getAttribute("data-read-maintenance-workshop") || "";
      const triggerEstimate =
        triggerButton.getAttribute("data-read-maintenance-estimate") || "";
      const triggerCost =
        triggerButton.getAttribute("data-read-maintenance-cost") || "";

      if (
        triggerVehicleId > 0 ||
        triggerIssue ||
        triggerWorkshop ||
        triggerEstimate ||
        triggerCost
      ) {
        return {
          vehicleId: triggerVehicleId,
          vehicleStatus: (
            triggerButton.getAttribute("data-read-vehicle-status") ||
            "maintenance"
          ).toLowerCase(),
          fleetMode:
            triggerButton.getAttribute("data-delete-fleet-mode") || "status",
          fleetType:
            triggerButton.getAttribute("data-delete-fleet-type") || "cars",
          fleetStatus:
            triggerButton.getAttribute("data-delete-fleet-status") ||
            "maintenance",
          maintenanceIssueDescription: triggerIssue,
          maintenanceWorkshop: triggerWorkshop,
          maintenanceEstimateDatetime: triggerEstimate,
          maintenanceCost: triggerCost,
        };
      }
    }

    return readModalContext;
  };

  // maintenance edit/fillup form: populate create modal with currently selected available vehicle context.
  const hydrateMaintenanceFillModal = (triggerButton = null) => {
    const maintenanceContext = resolveMaintenanceContext(triggerButton);
    if (!maintenanceContext) {
      return;
    }

    if (adminMaintenanceFillForm instanceof HTMLFormElement) {
      adminMaintenanceFillForm.reset();
    }

    if (adminMaintenanceFillVehicleIdInput instanceof HTMLInputElement) {
      adminMaintenanceFillVehicleIdInput.value = String(
        maintenanceContext.vehicleId || "",
      );
    }

    if (adminMaintenanceFillFleetModeInput instanceof HTMLInputElement) {
      adminMaintenanceFillFleetModeInput.value =
        maintenanceContext.fleetMode || "type";
    }

    if (adminMaintenanceFillFleetTypeInput instanceof HTMLInputElement) {
      adminMaintenanceFillFleetTypeInput.value =
        maintenanceContext.fleetType || "cars";
    }

    if (adminMaintenanceFillFleetStatusInput instanceof HTMLInputElement) {
      adminMaintenanceFillFleetStatusInput.value =
        maintenanceContext.fleetStatus || "maintenance";
    }

    if (adminMaintenanceFillIssueInput instanceof HTMLInputElement) {
      adminMaintenanceFillIssueInput.value =
        maintenanceContext.maintenanceIssueDescription || "";
    }

    if (adminMaintenanceFillWorkshopInput instanceof HTMLInputElement) {
      adminMaintenanceFillWorkshopInput.value =
        maintenanceContext.maintenanceWorkshop || "";
    }

    if (adminMaintenanceFillEstimateInput instanceof HTMLInputElement) {
      adminMaintenanceFillEstimateInput.value = toDateInputValue(
        maintenanceContext.maintenanceEstimateDatetime || "",
      );
    }

    if (adminMaintenanceFillCostInput instanceof HTMLInputElement) {
      adminMaintenanceFillCostInput.value =
        maintenanceContext.maintenanceCost || "";
    }
  };

  // maintenance edit/fillup form: populate maintenance edit modal and complete-service form from read modal context.
  const hydrateMaintenanceEditModal = (triggerButton = null) => {
    const maintenanceContext = resolveMaintenanceContext(triggerButton);
    if (!maintenanceContext) {
      return;
    }

    if (adminMaintenanceEditVehicleIdInput instanceof HTMLInputElement) {
      adminMaintenanceEditVehicleIdInput.value = String(
        maintenanceContext.vehicleId || "",
      );
    }

    if (adminMaintenanceEditFleetModeInput instanceof HTMLInputElement) {
      adminMaintenanceEditFleetModeInput.value =
        maintenanceContext.fleetMode || "status";
    }

    if (adminMaintenanceEditFleetTypeInput instanceof HTMLInputElement) {
      adminMaintenanceEditFleetTypeInput.value =
        maintenanceContext.fleetType || "cars";
    }

    if (adminMaintenanceEditFleetStatusInput instanceof HTMLInputElement) {
      adminMaintenanceEditFleetStatusInput.value =
        maintenanceContext.fleetStatus || "maintenance";
    }

    if (adminMaintenanceEditIssueInput instanceof HTMLInputElement) {
      adminMaintenanceEditIssueInput.value =
        maintenanceContext.maintenanceIssueDescription || "";
    }

    if (adminMaintenanceEditWorkshopInput instanceof HTMLInputElement) {
      adminMaintenanceEditWorkshopInput.value =
        maintenanceContext.maintenanceWorkshop || "";
    }

    if (adminMaintenanceEditEstimateInput instanceof HTMLInputElement) {
      adminMaintenanceEditEstimateInput.value = toDateInputValue(
        maintenanceContext.maintenanceEstimateDatetime || "",
      );
    }

    if (adminMaintenanceEditCostInput instanceof HTMLInputElement) {
      adminMaintenanceEditCostInput.value =
        maintenanceContext.maintenanceCost || "";
    }

    if (adminMaintenanceCompleteVehicleIdInput instanceof HTMLInputElement) {
      adminMaintenanceCompleteVehicleIdInput.value = String(
        maintenanceContext.vehicleId || "",
      );
    }

    if (adminMaintenanceCompleteFleetModeInput instanceof HTMLInputElement) {
      adminMaintenanceCompleteFleetModeInput.value =
        maintenanceContext.fleetMode || "status";
    }

    if (adminMaintenanceCompleteFleetTypeInput instanceof HTMLInputElement) {
      adminMaintenanceCompleteFleetTypeInput.value =
        maintenanceContext.fleetType || "cars";
    }

    if (adminMaintenanceCompleteFleetStatusInput instanceof HTMLInputElement) {
      adminMaintenanceCompleteFleetStatusInput.value =
        maintenanceContext.fleetStatus || "maintenance";
    }

    readModalContext = maintenanceContext;
  };

  const closeCreateCustomSelect = (controller = null) => {
    const targetController = controller || openCreateCustomSelectController;
    if (!targetController) {
      return;
    }

    targetController.shell.classList.remove("is-open");
    targetController.shell.classList.remove("is-drop-up");
    targetController.list.hidden = true;
    targetController.list.style.maxHeight = "";
    targetController.trigger.setAttribute("aria-expanded", "false");

    if (openCreateCustomSelectController === targetController) {
      openCreateCustomSelectController = null;
    }
  };

  const updateCreateCustomSelectViewport = (controller) => {
    if (!controller) {
      return;
    }

    const dialog = controller.shell.closest(".menu-modal__dialog");
    const triggerRect = controller.trigger.getBoundingClientRect();
    const dialogRect = dialog ? dialog.getBoundingClientRect() : null;

    const viewportTop = 0;
    const viewportBottom = window.innerHeight;
    const boundaryTop = dialogRect
      ? Math.max(dialogRect.top, viewportTop)
      : viewportTop;
    const boundaryBottom = dialogRect
      ? Math.min(dialogRect.bottom, viewportBottom)
      : viewportBottom;

    const spacing = 10;
    const preferredMaxHeight = 220;
    const minimumUsableHeight = 72;
    const availableBelow = Math.floor(
      boundaryBottom - triggerRect.bottom - spacing,
    );
    const availableAbove = Math.floor(triggerRect.top - boundaryTop - spacing);
    const shouldOpenUpward =
      availableBelow < 140 && availableAbove > availableBelow;

    const availableSpace = shouldOpenUpward ? availableAbove : availableBelow;
    const cappedHeight = Math.max(
      minimumUsableHeight,
      Math.min(preferredMaxHeight, availableSpace),
    );

    controller.shell.classList.toggle("is-drop-up", shouldOpenUpward);
    controller.list.style.maxHeight = `${cappedHeight}px`;
  };

  const syncCreateCustomSelect = (controller) => {
    if (!controller) {
      return;
    }

    const selectedOption =
      controller.nativeSelect.options[controller.nativeSelect.selectedIndex] ||
      controller.nativeSelect.options[0] ||
      null;
    const selectedValue = selectedOption ? selectedOption.value : "";
    const selectedLabel = selectedOption
      ? String(selectedOption.textContent || "").trim()
      : "";

    controller.value.textContent = selectedLabel;

    controller.items.forEach((item) => {
      const isSelected = item.dataset.value === selectedValue;
      item.classList.toggle("is-selected", isSelected);
      item.setAttribute("aria-selected", isSelected ? "true" : "false");
    });
  };

  const syncAllCreateCustomSelects = () => {
    createCustomSelectControllers.forEach((controller) => {
      syncCreateCustomSelect(controller);
    });
  };

  const initCreateCustomSelects = () => {
    if (adminCreateSelectInputs.length === 0) {
      return;
    }

    adminCreateSelectInputs.forEach((nativeSelect) => {
      if (
        !(nativeSelect instanceof HTMLSelectElement) ||
        nativeSelect.dataset.customSelectReady === "true"
      ) {
        return;
      }

      nativeSelect.dataset.customSelectReady = "true";
      nativeSelect.classList.add("admin-create-vehicle-modal__native-select");

      const shell = document.createElement("div");
      shell.className = "admin-create-vehicle-modal__select-shell";

      const trigger = document.createElement("button");
      trigger.type = "button";
      trigger.className = "admin-create-vehicle-modal__select-control";
      trigger.setAttribute("aria-haspopup", "listbox");
      trigger.setAttribute("aria-expanded", "false");

      const value = document.createElement("span");
      value.className = "admin-create-vehicle-modal__select-value";

      const icon = document.createElement("span");
      icon.className =
        "material-symbols-rounded admin-create-vehicle-modal__select-icon";
      icon.setAttribute("aria-hidden", "true");
      icon.textContent = "expand_more";

      trigger.append(value, icon);

      const list = document.createElement("ul");
      list.className = "admin-create-vehicle-modal__select-options";
      list.setAttribute("role", "listbox");
      list.hidden = true;

      const controller = {
        nativeSelect,
        shell,
        trigger,
        value,
        list,
        items: [],
      };

      Array.from(nativeSelect.options).forEach((option) => {
        const optionItem = document.createElement("li");

        const optionButton = document.createElement("button");
        optionButton.type = "button";
        optionButton.className = "admin-create-vehicle-modal__select-option";
        optionButton.dataset.value = option.value;
        optionButton.setAttribute("role", "option");
        optionButton.textContent = String(option.textContent || option.value);

        optionButton.addEventListener("click", () => {
          nativeSelect.value = option.value;
          nativeSelect.dispatchEvent(new Event("change", { bubbles: true }));
          syncCreateCustomSelect(controller);
          closeCreateCustomSelect(controller);
        });

        optionItem.appendChild(optionButton);
        list.appendChild(optionItem);
        controller.items.push(optionButton);
      });

      trigger.addEventListener("click", () => {
        const isOpen = shell.classList.contains("is-open");
        if (isOpen) {
          closeCreateCustomSelect(controller);
          return;
        }

        if (openCreateCustomSelectController) {
          closeCreateCustomSelect(openCreateCustomSelectController);
        }

        shell.classList.add("is-open");
        list.hidden = false;
        updateCreateCustomSelectViewport(controller);
        trigger.setAttribute("aria-expanded", "true");
        openCreateCustomSelectController = controller;
      });

      nativeSelect.addEventListener("change", () => {
        syncCreateCustomSelect(controller);
      });

      shell.append(trigger, list);
      nativeSelect.insertAdjacentElement("afterend", shell);

      createCustomSelectControllers.push(controller);
      syncCreateCustomSelect(controller);
    });
  };

  // create vehicle modal: manage 4-step navigation, step validation, and file-label hydration.
  const getCreateVehicleStepTitle = (step) =>
    step <= 1 ? "Create Vehicle" : `Create Vehicle Part ${step}`;

  const getCreateVehicleStepPanel = (step) => {
    if (adminCreateStepPanels.length === 0) {
      return null;
    }

    return (
      adminCreateStepPanels.find((panel) => {
        const panelStep = Number.parseInt(
          panel.getAttribute("data-create-step-panel") || "0",
          10,
        );
        return panelStep === step;
      }) || null
    );
  };

  const setCreateVehicleStep = (requestedStep) => {
    if (adminCreateStepPanels.length === 0) {
      return;
    }

    const maxStep = createStep4Enabled
      ? adminCreateStepPanels.length
      : Math.min(3, adminCreateStepPanels.length);
    const boundedStep = Math.min(
      Math.max(Number.parseInt(String(requestedStep), 10) || 1, 1),
      maxStep,
    );

    createVehicleStep = boundedStep;

    adminCreateStepPanels.forEach((panel) => {
      const panelStep = Number.parseInt(
        panel.getAttribute("data-create-step-panel") || "0",
        10,
      );
      const isActive = panelStep === boundedStep;
      panel.hidden = !isActive;
      panel.classList.toggle("is-active", isActive);
    });

    if (adminCreateStepLabel instanceof HTMLElement) {
      adminCreateStepLabel.textContent = getCreateVehicleStepTitle(boundedStep);
    }

    // Ensure forward arrows (data-create-step-next) are hidden on last panel
    // and only visible for the active, non-final panels.
    try {
      const maxStep = createStep4Enabled
        ? adminCreateStepPanels.length
        : Math.min(3, adminCreateStepPanels.length);
      const nextButtons = adminCreateVehicleModal.querySelectorAll(
        "[data-create-step-next]",
      );
      nextButtons.forEach((btn) => {
        const parentPanel = btn.closest("[data-create-step-panel]");
        const panelStep = parentPanel
          ? Number.parseInt(
              parentPanel.getAttribute("data-create-step-panel") || "0",
              10,
            )
          : 0;
        setElementVisibility(
          btn,
          panelStep === boundedStep && panelStep < maxStep,
        );
      });

      // Also ensure the specific step3 submit/next visibility aligns with active panel
      if (adminCreateStep3NextButton instanceof HTMLElement) {
        setElementVisibility(
          adminCreateStep3NextButton,
          3 === boundedStep && createStep4Enabled,
        );
      }
      if (adminCreateStep3SubmitButton instanceof HTMLElement) {
        setElementVisibility(
          adminCreateStep3SubmitButton,
          3 === boundedStep && !createStep4Enabled,
        );
      }
      if (adminCreateStep4SubmitButton instanceof HTMLElement) {
        setElementVisibility(
          adminCreateStep4SubmitButton,
          4 === boundedStep && createStep4Enabled,
        );
      }
    } catch (err) {
      // defensive: if DOM not present just ignore
    }
  };

  const validateCreateVehicleStep = (step) => {
    const panel = getCreateVehicleStepPanel(step);
    if (!(panel instanceof HTMLElement)) {
      return true;
    }

    const requiredControls = Array.from(
      panel.querySelectorAll(
        "input[required], select[required], textarea[required]",
      ),
    );

    for (const control of requiredControls) {
      if (
        control instanceof HTMLInputElement &&
        control.type === "file" &&
        control.required &&
        control.files &&
        control.files.length === 0
      ) {
        control.reportValidity();
        return false;
      }

      if (
        typeof control.checkValidity === "function" &&
        !control.checkValidity()
      ) {
        control.reportValidity();
        return false;
      }
    }

    if (
      adminCreateLastServiceInput instanceof HTMLInputElement &&
      panel.contains(adminCreateLastServiceInput)
    ) {
      const isLastServiceValid = validateDateNotInFuture(
        adminCreateLastServiceInput,
        "Please enter a valid last service date.",
        "Last service date cannot be in the future.",
        true,
      );
      if (!isLastServiceValid) {
        return false;
      }
    }

    return true;
  };

  const maxVehicleImageFields = 5;

  const getImageFieldRows = (rootNode) =>
    rootNode instanceof HTMLElement
      ? Array.from(rootNode.querySelectorAll("[data-image-field-row]"))
      : [];

  const getImageInputFromRow = (rowNode) =>
    rowNode instanceof HTMLElement
      ? rowNode.querySelector("[data-image-file-input]")
      : null;

  const getImageNameInputFromRow = (rowNode) =>
    rowNode instanceof HTMLElement
      ? rowNode.querySelector("[data-image-name-input]")
      : null;

  const getImageClearButtonFromRow = (rowNode) =>
    rowNode instanceof HTMLElement
      ? rowNode.querySelector("[data-image-clear-button]")
      : null;

  const getImageAddButtonFromRow = (rowNode) =>
    rowNode instanceof HTMLElement
      ? rowNode.querySelector("[data-image-add-button]")
      : null;

  const getImageSectionRoot = (fieldsRoot) => {
    if (!(fieldsRoot instanceof HTMLElement)) {
      return null;
    }
    return fieldsRoot.closest(
      "[data-create-image-section], [data-edit-image-section]",
    );
  };

  const updateImageFieldRowsUi = (rootNode, options = {}) => {
    const rows = getImageFieldRows(rootNode);
    if (rows.length === 0) {
      return;
    }

    const normalizedOptions =
      options && typeof options === "object" ? options : {};
    const createMode = normalizedOptions.createMode === true;
    const firstRowDefaultText = String(
      normalizedOptions.firstRowDefaultText || "",
    ).trim();

    const hasAnySelectedFile = rows.some((rowNode) => {
      const rowInput = getImageInputFromRow(rowNode);
      return (
        rowInput instanceof HTMLInputElement &&
        rowInput.files &&
        rowInput.files.length > 0
      );
    });

    rows.forEach((rowNode, index) => {
      const rowInput = getImageInputFromRow(rowNode);
      const rowNameInput = getImageNameInputFromRow(rowNode);
      const rowClearButton = getImageClearButtonFromRow(rowNode);
      const rowAddButton = getImageAddButtonFromRow(rowNode);

      const hasExistingImage =
        rowNode instanceof HTMLElement &&
        rowNode.dataset.imageExisting === "true";
      const existingName =
        rowNode instanceof HTMLElement
          ? String(rowNode.dataset.imageExistingName || "")
          : "";

      const selectedFileName =
        rowInput instanceof HTMLInputElement &&
        rowInput.files &&
        rowInput.files.length > 0
          ? String(rowInput.files[0].name || "")
          : "";

      const fallbackText =
        selectedFileName === "" && index === 0 ? firstRowDefaultText : "";

      if (rowNameInput instanceof HTMLInputElement) {
        rowNameInput.value =
          selectedFileName || (hasExistingImage ? existingName : fallbackText);
      }

      if (rowClearButton instanceof HTMLElement) {
        rowClearButton.hidden = selectedFileName === "" && !hasExistingImage;
      }

      if (rowAddButton instanceof HTMLElement) {
        rowAddButton.hidden = rows.length >= maxVehicleImageFields;
      }

      if (rowInput instanceof HTMLInputElement) {
        if (createMode) {
          rowInput.required = !hasAnySelectedFile && index === 0;
        } else {
          rowInput.required = false;
        }
      }
    });
  };

  const clearFieldZone = (zoneNode) => {
    if (!(zoneNode instanceof HTMLElement)) {
      return;
    }

    while (zoneNode.firstChild) {
      zoneNode.removeChild(zoneNode.firstChild);
    }
  };

  const setElementVisibility = (node, isVisible) => {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    node.hidden = !isVisible;
    if (isVisible) {
      node.style.removeProperty("display");
    } else {
      node.style.setProperty("display", "none", "important");
    }
  };

  const fillFieldZone = (zoneNode, blocks, startIndex, maxCount) => {
    if (!(zoneNode instanceof HTMLElement)) {
      return startIndex;
    }

    let index = startIndex;
    let filled = 0;
    while (index < blocks.length && filled < maxCount) {
      zoneNode.appendChild(blocks[index]);
      index += 1;
      filled += 1;
    }

    return index;
  };

  const rebuildCreateFieldPages = () => {
    if (
      !adminCreateFieldZone2 ||
      !adminCreateFieldZone3 ||
      !adminCreateFieldZone4
    ) {
      return;
    }

    clearFieldZone(adminCreateFieldZone2);
    clearFieldZone(adminCreateFieldZone3);
    clearFieldZone(adminCreateFieldZone4);

    const imageCount = adminCreateImageFieldsRoot
      ? getImageFieldRows(adminCreateImageFieldsRoot).length
      : 0;
    const slotsPage2 = Math.max(0, maxFieldsPerPage - imageCount);

    let index = 0;
    index = fillFieldZone(
      adminCreateFieldZone2,
      adminCreateFieldBlocks,
      index,
      slotsPage2,
    );
    index = fillFieldZone(
      adminCreateFieldZone3,
      adminCreateFieldBlocks,
      index,
      maxFieldsPerPage,
    );
    fillFieldZone(
      adminCreateFieldZone4,
      adminCreateFieldBlocks,
      index,
      maxFieldsPerPage,
    );

    createStep4Enabled =
      adminCreateFieldZone4.children.length > 0 &&
      adminCreateFieldBlocks.length > 0;

    if (adminCreateStep3NextButton instanceof HTMLElement) {
      setElementVisibility(adminCreateStep3NextButton, createStep4Enabled);
    }

    if (adminCreateStep3SubmitButton instanceof HTMLElement) {
      setElementVisibility(adminCreateStep3SubmitButton, !createStep4Enabled);
    }

    if (adminCreateStep4SubmitButton instanceof HTMLElement) {
      setElementVisibility(adminCreateStep4SubmitButton, createStep4Enabled);
    }

    if (!createStep4Enabled && createVehicleStep === 4) {
      setCreateVehicleStep(3);
    }

    if (adminCreateStep4Panel instanceof HTMLElement) {
      if (!createStep4Enabled) {
        adminCreateStep4Panel.hidden = true;
        adminCreateStep4Panel.classList.remove("is-active");
      }
    }

    setCreateVehicleStep(createVehicleStep);
  };

  const rebuildEditFieldPages = () => {
    if (!adminEditFieldZone2 || !adminEditFieldZone3 || !adminEditFieldZone4) {
      return;
    }

    clearFieldZone(adminEditFieldZone2);
    clearFieldZone(adminEditFieldZone3);
    clearFieldZone(adminEditFieldZone4);

    const imageCount = adminEditImageFieldsRoot
      ? getImageFieldRows(adminEditImageFieldsRoot).length
      : 0;
    const slotsPage2 = Math.max(0, maxFieldsPerPage - imageCount);

    let index = 0;
    index = fillFieldZone(
      adminEditFieldZone2,
      adminEditFieldBlocks,
      index,
      slotsPage2,
    );
    index = fillFieldZone(
      adminEditFieldZone3,
      adminEditFieldBlocks,
      index,
      maxFieldsPerPage,
    );
    fillFieldZone(
      adminEditFieldZone4,
      adminEditFieldBlocks,
      index,
      maxFieldsPerPage,
    );

    editStep4Enabled =
      adminEditFieldZone4.children.length > 0 &&
      adminEditFieldBlocks.length > 0;

    if (adminEditStep3NextButton instanceof HTMLElement) {
      setElementVisibility(adminEditStep3NextButton, editStep4Enabled);
    }

    if (adminEditStep3SubmitButton instanceof HTMLElement) {
      setElementVisibility(adminEditStep3SubmitButton, !editStep4Enabled);
    }

    if (adminEditStep4SubmitButton instanceof HTMLElement) {
      setElementVisibility(adminEditStep4SubmitButton, editStep4Enabled);
    }

    if (!editStep4Enabled && editVehicleStep === 4) {
      setEditVehicleStep(3);
    }

    if (adminEditStep4Panel instanceof HTMLElement) {
      if (!editStep4Enabled) {
        adminEditStep4Panel.hidden = true;
        adminEditStep4Panel.classList.remove("is-active");
      }
    }

    setEditVehicleStep(editVehicleStep);
  };

  const appendImageFieldRow = (rootNode) => {
    const rows = getImageFieldRows(rootNode);
    if (rows.length === 0 || rows.length >= maxVehicleImageFields) {
      return;
    }

    const templateRow = rows[0];
    const clonedRow = templateRow.cloneNode(true);
    if (!(clonedRow instanceof HTMLElement)) {
      return;
    }

    clonedRow.dataset.imageExisting = "false";

    const clonedNameInput = getImageNameInputFromRow(clonedRow);
    if (clonedNameInput instanceof HTMLInputElement) {
      clonedNameInput.value = "";
      clonedNameInput.removeAttribute("id");
      clonedNameInput.removeAttribute("data-edit-image-name-input");
    }

    const clonedFileInput = getImageInputFromRow(clonedRow);
    if (clonedFileInput instanceof HTMLInputElement) {
      clonedFileInput.value = "";
      clonedFileInput.removeAttribute("id");
      clonedFileInput.required = false;
    }

    const clonedClearButton = getImageClearButtonFromRow(clonedRow);
    if (clonedClearButton instanceof HTMLElement) {
      clonedClearButton.hidden = true;
    }

    rootNode.appendChild(clonedRow);
  };

  const populateEditExistingImageRows = (
    rootNode,
    imagePaths = [],
    firstRowDefaultText = "",
  ) => {
    if (!(rootNode instanceof HTMLElement)) {
      return;
    }

    if (!Array.isArray(imagePaths) || imagePaths.length === 0) {
      return;
    }

    // Ensure first row exists
    let rows = getImageFieldRows(rootNode);
    if (rows.length === 0) {
      return;
    }

    const firstRow = rows[0];
    const firstNameInput = getImageNameInputFromRow(firstRow);
    const firstFileInput = getImageInputFromRow(firstRow);
    const firstClearButton = getImageClearButtonFromRow(firstRow);

    if (firstNameInput instanceof HTMLInputElement) {
      firstNameInput.value =
        getFileNameFromPath(imagePaths[0]) || firstRowDefaultText || "";
    }
    if (firstClearButton instanceof HTMLElement) {
      firstClearButton.hidden = false;
    }
    if (firstFileInput instanceof HTMLInputElement) {
      firstFileInput.required = false;
    }
    if (firstRow instanceof HTMLElement) {
      firstRow.dataset.imageExisting = imagePaths[0] ? "true" : "false";
      firstRow.dataset.imageExistingName =
        getFileNameFromPath(imagePaths[0]) || firstRowDefaultText || "";
    }

    // Append rows for remaining images
    for (
      let i = 1;
      i < Math.min(imagePaths.length, maxVehicleImageFields);
      i += 1
    ) {
      appendImageFieldRow(rootNode);
      rows = getImageFieldRows(rootNode);
      const row = rows[i];
      if (!(row instanceof HTMLElement)) {
        continue;
      }
      const nameInput = getImageNameInputFromRow(row);
      const fileInput = getImageInputFromRow(row);
      const clearButton = getImageClearButtonFromRow(row);
      if (nameInput instanceof HTMLInputElement) {
        nameInput.value = getFileNameFromPath(imagePaths[i]) || "";
      }
      if (clearButton instanceof HTMLElement) {
        clearButton.hidden = false;
      }
      if (fileInput instanceof HTMLInputElement) {
        fileInput.required = false;
      }
      row.dataset.imageExisting = "true";
      row.dataset.imageExistingName = getFileNameFromPath(imagePaths[i]) || "";
    }

    // Update UI state for add/clear buttons
    updateImageFieldRowsUi(rootNode, {
      createMode: false,
      firstRowDefaultText: firstRowDefaultText || imagePaths[0] || "",
      existingImagePath: imagePaths[0] || "",
    });
  };

  const removeImageFieldRow = (rowNode, rootNode) => {
    if (!(rowNode instanceof HTMLElement)) {
      return;
    }

    rowNode.remove();

    updateImageFieldRowsUi(rootNode, {
      createMode: rootNode.closest("[data-create-image-section]") !== null,
      firstRowDefaultText: "",
    });
  };

  const resetImageFieldRows = (rootNode, options = {}) => {
    const rows = getImageFieldRows(rootNode);
    if (rows.length === 0) {
      return;
    }

    for (let index = rows.length - 1; index >= 1; index -= 1) {
      rows[index].remove();
    }

    const firstRow = rows[0];
    const firstInput = getImageInputFromRow(firstRow);
    if (firstInput instanceof HTMLInputElement) {
      firstInput.value = "";
    }

    if (firstRow instanceof HTMLElement) {
      firstRow.dataset.imageExisting =
        options.existingImagePath &&
        String(options.existingImagePath).trim() !== ""
          ? "true"
          : "false";
      firstRow.dataset.imageExistingName = getFileNameFromPath(
        options.existingImagePath || "",
      );
    }

    updateImageFieldRowsUi(rootNode, options);
  };

  const updateCreateImageUi = () => {
    updateImageFieldRowsUi(adminCreateImageFieldsRoot, {
      createMode: true,
    });
    rebuildCreateFieldPages();
  };

  const resetCreateVehicleModal = () => {
    closeCreateCustomSelect();

    if (adminCreateVehicleForm instanceof HTMLFormElement) {
      adminCreateVehicleForm.reset();
    }

    resetImageFieldRows(adminCreateImageFieldsRoot, {
      createMode: true,
    });

    if (adminCreateFleetModeInput instanceof HTMLInputElement) {
      adminCreateFleetModeInput.value = "type";
    }

    if (adminCreateFleetTypeInput instanceof HTMLInputElement) {
      adminCreateFleetTypeInput.value = "cars";
    }

    if (adminCreateVehicleTypeInput instanceof HTMLSelectElement) {
      adminCreateVehicleTypeInput.value = "cars";
    }

    syncAllCreateCustomSelects();
    setCreateVehicleStep(1);
    updateCreateImageUi();
  };

  const hydrateCreateVehicleModal = (triggerButton) => {
    resetCreateVehicleModal();

    if (!(triggerButton instanceof HTMLElement)) {
      return;
    }

    const fleetMode =
      triggerButton.getAttribute("data-create-fleet-mode") || "type";
    const fleetType =
      triggerButton.getAttribute("data-create-fleet-type") || "cars";
    const vehicleType =
      triggerButton.getAttribute("data-create-vehicle-type") || fleetType;

    if (adminCreateFleetModeInput instanceof HTMLInputElement) {
      adminCreateFleetModeInput.value = fleetMode;
    }

    if (adminCreateFleetTypeInput instanceof HTMLInputElement) {
      adminCreateFleetTypeInput.value = fleetType;
    }

    if (adminCreateVehicleTypeInput instanceof HTMLSelectElement) {
      adminCreateVehicleTypeInput.value = vehicleType;
      if (adminCreateFleetTypeInput instanceof HTMLInputElement) {
        adminCreateFleetTypeInput.value = vehicleType;
      }
    }

    syncAllCreateCustomSelects();
  };

  // edit vehicle modal: resolve trigger context, hydrate 3-step form state, and keep image/select UI in sync.
  const getFileNameFromPath = (rawPath) => {
    const normalized = String(rawPath || "").trim();
    if (normalized === "") {
      return "";
    }

    const segments = normalized.split(/[\\/]+/);
    return segments.length > 0 ? segments[segments.length - 1] : normalized;
  };

  const normalizeEditVehicleStatus = (rawStatus) => {
    const normalized = String(rawStatus || "available")
      .trim()
      .toLowerCase();
    if (Object.prototype.hasOwnProperty.call(readStatusLabels, normalized)) {
      return normalized;
    }

    return "available";
  };

  const resolveEditVehicleContext = (triggerButton = null) => {
    if (triggerButton instanceof HTMLElement) {
      const triggerVehicleId = Number.parseInt(
        triggerButton.getAttribute("data-edit-vehicle-id") ||
          triggerButton.getAttribute("data-read-vehicle-id") ||
          "0",
        10,
      );
      const triggerFullName =
        triggerButton.getAttribute("data-edit-vehicle-full-name") ||
        triggerButton.getAttribute("data-read-vehicle-full-name") ||
        "";
      const triggerShortName =
        triggerButton.getAttribute("data-edit-vehicle-short-name") ||
        triggerButton.getAttribute("data-read-vehicle-name") ||
        "";
      const triggerLicense =
        triggerButton.getAttribute("data-edit-vehicle-license") ||
        triggerButton.getAttribute("data-edit-vehicle-license-plate") ||
        triggerButton.getAttribute("data-read-vehicle-plate") ||
        "";

      if (
        triggerVehicleId > 0 ||
        triggerFullName ||
        triggerShortName ||
        triggerLicense
      ) {
        return {
          vehicleId: triggerVehicleId,
          vehicleStatus: normalizeEditVehicleStatus(
            triggerButton.getAttribute("data-edit-vehicle-status") ||
              triggerButton.getAttribute("data-read-vehicle-status") ||
              "available",
          ),
          fleetMode:
            triggerButton.getAttribute("data-delete-fleet-mode") || "type",
          fleetType:
            triggerButton.getAttribute("data-delete-fleet-type") || "cars",
          fleetStatus:
            triggerButton.getAttribute("data-delete-fleet-status") ||
            "available",
          vehicleType:
            triggerButton.getAttribute("data-edit-vehicle-type") ||
            triggerButton.getAttribute("data-read-vehicle-type") ||
            "cars",
          vehicleFullName: triggerFullName,
          vehicleShortName: triggerShortName,
          vehiclePricePerDay:
            triggerButton.getAttribute("data-edit-vehicle-price") ||
            triggerButton.getAttribute("data-read-vehicle-price") ||
            "",
          vehicleDriverAge:
            triggerButton.getAttribute("data-edit-vehicle-driver-age") ||
            triggerButton.getAttribute("data-read-vehicle-age") ||
            "",
          vehicleSeats:
            triggerButton.getAttribute("data-edit-vehicle-seats") ||
            triggerButton.getAttribute("data-read-vehicle-seats") ||
            "",
          vehicleTransmission:
            triggerButton.getAttribute("data-edit-vehicle-transmission") ||
            triggerButton.getAttribute("data-read-vehicle-transmission") ||
            "manual",
          vehicleFuel:
            triggerButton.getAttribute("data-edit-vehicle-fuel") ||
            triggerButton.getAttribute("data-read-vehicle-fuel") ||
            "petrol",
          vehicleMileage:
            triggerButton.getAttribute("data-edit-vehicle-mileage") ||
            triggerButton.getAttribute("data-read-vehicle-mileage") ||
            "",
          vehicleLicensePlate: triggerLicense,
          vehicleGpsId:
            triggerButton.getAttribute("data-edit-vehicle-gps-id") ||
            triggerButton.getAttribute("data-read-vehicle-gps-id") ||
            "",
          vehicleLastServiceDate:
            triggerButton.getAttribute("data-edit-vehicle-last-service") ||
            triggerButton.getAttribute("data-read-vehicle-last-service") ||
            "",
          vehicleDescription:
            triggerButton.getAttribute("data-edit-vehicle-description") ||
            triggerButton.getAttribute("data-read-vehicle-description") ||
            "",
          vehicleImagePath:
            triggerButton.getAttribute("data-edit-vehicle-image") ||
            triggerButton.getAttribute("data-read-vehicle-image") ||
            "",
        };
      }
    }

    return readModalContext;
  };

  const getEditVehicleStepPanel = (step) => {
    if (adminEditStepPanels.length === 0) {
      return null;
    }

    return (
      adminEditStepPanels.find((panel) => {
        const panelStep = Number.parseInt(
          panel.getAttribute("data-edit-step-panel") || "0",
          10,
        );
        return panelStep === step;
      }) || null
    );
  };

  const setEditVehicleStep = (requestedStep) => {
    if (adminEditStepPanels.length === 0) {
      return;
    }

    const maxStep = editStep4Enabled
      ? adminEditStepPanels.length
      : Math.min(3, adminEditStepPanels.length);
    const boundedStep = Math.min(
      Math.max(Number.parseInt(String(requestedStep), 10) || 1, 1),
      maxStep,
    );

    editVehicleStep = boundedStep;

    adminEditStepPanels.forEach((panel) => {
      const panelStep = Number.parseInt(
        panel.getAttribute("data-edit-step-panel") || "0",
        10,
      );
      const isActive = panelStep === boundedStep;
      panel.hidden = !isActive;
      panel.classList.toggle("is-active", isActive);
    });

    // Ensure forward arrows/next buttons are hidden on last panel and only
    // visible for the active, non-final panels in edit flow.
    try {
      const maxStep = editStep4Enabled
        ? adminEditStepPanels.length
        : Math.min(3, adminEditStepPanels.length);
      const nextButtons = adminEditVehicleModal.querySelectorAll(
        "[data-edit-step-next]",
      );
      nextButtons.forEach((btn) => {
        const parentPanel = btn.closest("[data-edit-step-panel]");
        const panelStep = parentPanel
          ? Number.parseInt(
              parentPanel.getAttribute("data-edit-step-panel") || "0",
              10,
            )
          : 0;
        setElementVisibility(
          btn,
          panelStep === boundedStep && panelStep < maxStep,
        );
      });

      if (adminEditStep3NextButton instanceof HTMLElement) {
        setElementVisibility(
          adminEditStep3NextButton,
          3 === boundedStep && editStep4Enabled,
        );
      }
      if (adminEditStep3SubmitButton instanceof HTMLElement) {
        setElementVisibility(
          adminEditStep3SubmitButton,
          3 === boundedStep && !editStep4Enabled,
        );
      }
      if (adminEditStep4SubmitButton instanceof HTMLElement) {
        setElementVisibility(
          adminEditStep4SubmitButton,
          4 === boundedStep && editStep4Enabled,
        );
      }
    } catch (err) {
      // defensive noop
    }
  };

  const validateEditVehicleStep = (step) => {
    const panel = getEditVehicleStepPanel(step);
    if (!(panel instanceof HTMLElement)) {
      return true;
    }

    const requiredControls = Array.from(
      panel.querySelectorAll(
        "input[required], select[required], textarea[required]",
      ),
    );

    for (const control of requiredControls) {
      if (
        typeof control.checkValidity === "function" &&
        !control.checkValidity()
      ) {
        control.reportValidity();
        return false;
      }
    }

    if (
      adminEditLastServiceInput instanceof HTMLInputElement &&
      panel.contains(adminEditLastServiceInput)
    ) {
      const isLastServiceValid = validateDateNotInFuture(
        adminEditLastServiceInput,
        "Please enter a valid last service date.",
        "Last service date cannot be in the future.",
        true,
      );
      if (!isLastServiceValid) {
        return false;
      }
    }

    return true;
  };

  const updateEditImageUi = () => {
    const currentImageName = getFileNameFromPath(
      adminEditCurrentImagePathInput instanceof HTMLInputElement
        ? adminEditCurrentImagePathInput.value
        : "",
    );

    if (
      adminEditReplacePrimaryImageInput instanceof HTMLInputElement &&
      adminEditImageFieldsRoot instanceof HTMLElement
    ) {
      const rows = getImageFieldRows(adminEditImageFieldsRoot);
      const firstRow = rows[0];
      const firstInput = getImageInputFromRow(firstRow);
      const hasFile =
        firstInput instanceof HTMLInputElement &&
        firstInput.files &&
        firstInput.files.length > 0;
      adminEditReplacePrimaryImageInput.value = hasFile ? "1" : "0";
    }

    updateImageFieldRowsUi(adminEditImageFieldsRoot, {
      createMode: false,
      firstRowDefaultText: currentImageName || "Keep current image",
    });
    rebuildEditFieldPages();
  };

  const resetEditVehicleModal = () => {
    closeCreateCustomSelect();

    if (adminEditVehicleForm instanceof HTMLFormElement) {
      adminEditVehicleForm.reset();
    }

    if (adminEditCurrentImagePathInput instanceof HTMLInputElement) {
      adminEditCurrentImagePathInput.value = "";
    }

    if (adminEditRemoveCurrentImageInput instanceof HTMLInputElement) {
      adminEditRemoveCurrentImageInput.value = "0";
    }

    if (adminEditReplacePrimaryImageInput instanceof HTMLInputElement) {
      adminEditReplacePrimaryImageInput.value = "0";
    }

    if (adminEditFleetModeInput instanceof HTMLInputElement) {
      adminEditFleetModeInput.value = "type";
    }

    if (adminEditFleetTypeInput instanceof HTMLInputElement) {
      adminEditFleetTypeInput.value = "cars";
    }

    if (adminEditFleetStatusInput instanceof HTMLInputElement) {
      adminEditFleetStatusInput.value = "available";
    }

    if (adminEditVehicleTypeInput instanceof HTMLSelectElement) {
      adminEditVehicleTypeInput.value = "cars";
    }

    if (adminEditDriverAgeInput instanceof HTMLSelectElement) {
      adminEditDriverAgeInput.value = "18";
    }

    if (adminEditStatusInput instanceof HTMLSelectElement) {
      adminEditStatusInput.value = "available";
    }

    resetImageFieldRows(adminEditImageFieldsRoot, {
      createMode: false,
      firstRowDefaultText: "Keep current image",
      existingImagePath: "",
    });

    syncAllCreateCustomSelects();
    setEditVehicleStep(1);
    updateEditImageUi();
  };

  const hydrateEditVehicleModal = (triggerButton = null) => {
    const editContext = resolveEditVehicleContext(triggerButton);
    if (!editContext) {
      return;
    }

    resetEditVehicleModal();

    const normalizedVehicleType =
      String(editContext.vehicleType || "cars").toLowerCase() || "cars";
    const normalizedVehicleStatus = normalizeEditVehicleStatus(
      editContext.vehicleStatus,
    );
    const normalizedDriverAge = String(
      editContext.vehicleDriverAge || "18",
    ).trim();
    const sanitizedDriverAge =
      normalizedDriverAge === "21" || normalizedDriverAge === "18"
        ? normalizedDriverAge
        : "18";

    if (adminEditVehicleIdInput instanceof HTMLInputElement) {
      adminEditVehicleIdInput.value = String(editContext.vehicleId || "");
    }

    if (adminEditFleetModeInput instanceof HTMLInputElement) {
      adminEditFleetModeInput.value = editContext.fleetMode || "type";
    }

    if (adminEditFleetTypeInput instanceof HTMLInputElement) {
      adminEditFleetTypeInput.value = editContext.fleetType || "cars";
    }

    if (adminEditFleetStatusInput instanceof HTMLInputElement) {
      adminEditFleetStatusInput.value =
        editContext.fleetStatus || normalizedVehicleStatus;
    }

    if (adminEditVehicleTypeInput instanceof HTMLSelectElement) {
      adminEditVehicleTypeInput.value = normalizedVehicleType;
      if (adminEditFleetTypeInput instanceof HTMLInputElement) {
        adminEditFleetTypeInput.value = normalizedVehicleType;
      }
    }

    if (adminEditFullNameInput instanceof HTMLInputElement) {
      adminEditFullNameInput.value = editContext.vehicleFullName || "";
    }

    if (adminEditShortNameInput instanceof HTMLInputElement) {
      adminEditShortNameInput.value = editContext.vehicleShortName || "";
    }

    if (adminEditPriceInput instanceof HTMLInputElement) {
      adminEditPriceInput.value = editContext.vehiclePricePerDay || "";
    }

    if (adminEditDriverAgeInput instanceof HTMLSelectElement) {
      adminEditDriverAgeInput.value = sanitizedDriverAge;
    }

    if (adminEditSeatsInput instanceof HTMLInputElement) {
      adminEditSeatsInput.value = editContext.vehicleSeats || "";
    }

    if (adminEditTransmissionInput instanceof HTMLSelectElement) {
      adminEditTransmissionInput.value =
        editContext.vehicleTransmission || "manual";
    }

    if (adminEditFuelInput instanceof HTMLSelectElement) {
      adminEditFuelInput.value = editContext.vehicleFuel || "petrol";
    }

    if (adminEditMileageInput instanceof HTMLInputElement) {
      const rawMileage = String(editContext.vehicleMileage || "").trim();
      const parsedMileage = Number.parseFloat(rawMileage);
      adminEditMileageInput.value = Number.isFinite(parsedMileage)
        ? parsedMileage.toFixed(2)
        : "";
    }

    if (adminEditLicenseInput instanceof HTMLInputElement) {
      adminEditLicenseInput.value = editContext.vehicleLicensePlate || "";
    }

    if (adminEditGpsInput instanceof HTMLInputElement) {
      adminEditGpsInput.value = editContext.vehicleGpsId || "";
    }

    if (adminEditStatusInput instanceof HTMLSelectElement) {
      adminEditStatusInput.value = normalizedVehicleStatus;
    }

    if (adminEditLastServiceInput instanceof HTMLInputElement) {
      adminEditLastServiceInput.value = toDateInputValue(
        editContext.vehicleLastServiceDate || "",
      );
    }

    if (adminEditDescriptionInput instanceof HTMLTextAreaElement) {
      adminEditDescriptionInput.value = editContext.vehicleDescription || "";
    }

    if (adminEditCurrentImagePathInput instanceof HTMLInputElement) {
      adminEditCurrentImagePathInput.value = editContext.vehicleImagePath || "";
    }

    if (adminEditRemoveCurrentImageInput instanceof HTMLInputElement) {
      adminEditRemoveCurrentImageInput.value = "0";
    }

    resetImageFieldRows(adminEditImageFieldsRoot, {
      createMode: false,
      firstRowDefaultText: editContext.vehicleImagePath || "",
      existingImagePath: editContext.vehicleImagePath || "",
    });

    // Try to fetch existing gallery images for this vehicle and populate rows
    (async () => {
      try {
        const vehicleId =
          Number.parseInt(
            String(
              editContext.vehicleId || adminEditVehicleIdInput?.value || "0",
            ),
            10,
          ) || 0;
        if (vehicleId > 0 && adminEditImageFieldsRoot instanceof HTMLElement) {
          const resp = await fetch(
            "ajax/get-vehicle-images.php?vehicle_id=" +
              encodeURIComponent(vehicleId),
            { credentials: "same-origin" },
          );
          if (resp && resp.ok) {
            const data = await resp.json();
            if (Array.isArray(data) && data.length > 0) {
              populateEditExistingImageRows(
                adminEditImageFieldsRoot,
                data,
                editContext.vehicleImagePath || "",
              );
            }
          }
        }
      } catch (err) {
        // ignore fetch errors silently
      } finally {
        syncAllCreateCustomSelects();
        setEditVehicleStep(1);
        updateEditImageUi();
      }
    })();

    readModalContext = {
      ...(readModalContext && typeof readModalContext === "object"
        ? readModalContext
        : {}),
      ...editContext,
      vehicleStatus: normalizedVehicleStatus,
      vehicleType: normalizedVehicleType,
    };
  };

  initCreateCustomSelects();

  if (adminReadEditAction instanceof HTMLElement) {
    adminReadEditAction.addEventListener("click", () => {
      const maintenanceContext = resolveMaintenanceContext(adminReadEditAction);
      const editContext = resolveEditVehicleContext(adminReadEditAction);

      if (!maintenanceContext && !editContext) {
        return;
      }

      if (maintenanceContext) {
        readModalContext = maintenanceContext;
      }

      const targetId =
        maintenanceContext?.vehicleStatus === "maintenance"
          ? "admin-maintenance-edit-modal"
          : "admin-edit-vehicle-modal";

      if (targetId === "admin-maintenance-edit-modal") {
        hydrateMaintenanceEditModal(adminReadEditAction);
      } else {
        hydrateEditVehicleModal(adminReadEditAction);
      }

      if (modalHistory.length === 0) {
        lastFocusedElement =
          document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
      }

      showModal(targetId, true);
    });
  }

  const hydrateReadVehicleModal = (triggerButton) => {
    if (!(triggerButton instanceof HTMLElement)) {
      return;
    }

    const vehicleId = Number.parseInt(
      triggerButton.getAttribute("data-read-vehicle-id") || "0",
      10,
    );
    const vehicleName =
      triggerButton.getAttribute("data-read-vehicle-name") || "Vehicle";
    const vehicleFullName =
      triggerButton.getAttribute("data-read-vehicle-full-name") || vehicleName;
    const vehicleTypeRaw = (
      triggerButton.getAttribute("data-read-vehicle-type") || "cars"
    ).toLowerCase();
    const vehicleStatusRaw = (
      triggerButton.getAttribute("data-read-vehicle-status") || "available"
    ).toLowerCase();
    const vehicleStatus = Object.prototype.hasOwnProperty.call(
      readStatusLabels,
      vehicleStatusRaw,
    )
      ? vehicleStatusRaw
      : "available";
    const vehicleImagePath =
      triggerButton.getAttribute("data-read-vehicle-image") ||
      "images/vehicle-feature.png";
    const vehiclePricePerDay =
      triggerButton.getAttribute("data-read-vehicle-price") || "";
    const vehicleDriverAge =
      triggerButton.getAttribute("data-read-vehicle-age") || "";
    const vehicleSeats =
      triggerButton.getAttribute("data-read-vehicle-seats") || "";
    const vehicleTransmission =
      triggerButton.getAttribute("data-read-vehicle-transmission") || "";
    const vehicleFuel =
      triggerButton.getAttribute("data-read-vehicle-fuel") || "";
    const vehicleMileage =
      triggerButton.getAttribute("data-read-vehicle-mileage") ||
      triggerButton.getAttribute("data-edit-vehicle-mileage") ||
      "";
    const vehicleLicensePlate =
      triggerButton.getAttribute("data-read-vehicle-plate") || "";
    const vehicleGpsId =
      triggerButton.getAttribute("data-read-vehicle-gps-id") || "";
    const vehicleLastServiceDate =
      triggerButton.getAttribute("data-read-vehicle-last-service") || "";
    const vehicleDescription =
      triggerButton.getAttribute("data-read-vehicle-description") || "";
    const fleetModeForReadModal =
      triggerButton.getAttribute("data-delete-fleet-mode") || "type";

    const bookingNumber =
      triggerButton.getAttribute("data-read-booking-number") || "";
    const bookingId = Number.parseInt(
      triggerButton.getAttribute("data-read-booking-id") || "0",
      10,
    );
    const bookingUserName =
      triggerButton.getAttribute("data-read-booking-user-name") || "";
    const bookingUserPhone =
      triggerButton.getAttribute("data-read-booking-user-phone") || "";
    const pickupDatetime =
      triggerButton.getAttribute("data-read-booking-pickup") || "";
    const returnDatetime =
      triggerButton.getAttribute("data-read-booking-return") || "";
    const paymentStatus =
      triggerButton.getAttribute("data-read-booking-payment-status") || "";
    const lateFee =
      triggerButton.getAttribute("data-read-booking-late-fee") || "";
    const latitude = triggerButton.getAttribute("data-read-gps-latitude") || "";
    const longitude =
      triggerButton.getAttribute("data-read-gps-longitude") || "";
    const parsedLatitude = Number.parseFloat(String(latitude).trim());
    const parsedLongitude = Number.parseFloat(String(longitude).trim());
    const hasGpsSignal =
      Number.isFinite(parsedLatitude) &&
      Number.isFinite(parsedLongitude) &&
      (Math.abs(parsedLatitude) > 0.00001 ||
        Math.abs(parsedLongitude) > 0.00001);
    const currentLocation = hasGpsSignal
      ? `${parsedLatitude.toFixed(6)}, ${parsedLongitude.toFixed(6)}`
      : "";

    const resolvedBookingNumber =
      !isReadValueMissing(bookingNumber) ||
      !Number.isFinite(bookingId) ||
      bookingId <= 0
        ? bookingNumber
        : `#BK-${String(bookingId).padStart(4, "0")}`;

    const hasBookingTimelineData =
      !isReadValueMissing(resolvedBookingNumber) ||
      !isReadValueMissing(pickupDatetime) ||
      !isReadValueMissing(returnDatetime) ||
      !isReadValueMissing(paymentStatus);
    const resolvedVehicleStatus =
      readUserStatuses.includes(vehicleStatus) && !hasBookingTimelineData
        ? "available"
        : vehicleStatus;

    const details = {
      lastServiceDate: vehicleLastServiceDate,
      description: vehicleDescription,
      maintenanceIssueDescription:
        triggerButton.getAttribute("data-read-maintenance-issue") || "",
      maintenanceWorkshop:
        triggerButton.getAttribute("data-read-maintenance-workshop") || "",
      maintenanceEstimateDatetime:
        triggerButton.getAttribute("data-read-maintenance-estimate") || "",
      maintenanceCost:
        triggerButton.getAttribute("data-read-maintenance-cost") || "",
      upcomingPickupDatetime:
        triggerButton.getAttribute("data-read-upcoming-pickup") || "",
      totalReservations:
        triggerButton.getAttribute("data-read-total-reservations") || "0",
      totalEarnings:
        triggerButton.getAttribute("data-read-total-earnings") || "0",
      pickupDatetime,
      returnDatetime,
      paymentStatus,
      lateFee,
      currentLocation,
    };

    const isUserStatus = readUserStatuses.includes(resolvedVehicleStatus);

    if (adminReadBookingNumberNode instanceof HTMLElement) {
      adminReadBookingNumberNode.textContent = isUserStatus
        ? normalizeReadValue(resolvedBookingNumber, resolvedVehicleStatus, true)
        : readUserNotApplicableText;
    }

    if (
      adminReadCurrentUserLine instanceof HTMLElement &&
      adminReadCurrentUserNode instanceof HTMLElement
    ) {
      adminReadCurrentUserLine.hidden = !isUserStatus;

      if (!isUserStatus) {
        adminReadCurrentUserNode.textContent = "";
      } else {
        const userDetails = [bookingUserName, bookingUserPhone]
          .map((value) => String(value || "").trim())
          .filter((value) => value !== "");
        adminReadCurrentUserNode.textContent =
          userDetails.length > 0 ? userDetails.join(" ") : readUnavailableText;
      }
    }

    if (adminReadStatusPill instanceof HTMLElement) {
      adminReadStatusPill.textContent =
        readStatusLabels[resolvedVehicleStatus] || "Available";
      adminReadStatusPill.className = `admin-vehicle-read-modal__status-pill admin-vehicle-read-modal__status-pill--${resolvedVehicleStatus.replace("_", "-")}`;
    }

    if (adminReadMaintenanceIndicator instanceof HTMLElement) {
      const showMaintenanceIndicator =
        fleetModeForReadModal === "type" &&
        resolvedVehicleStatus === "available";
      adminReadMaintenanceIndicator.hidden = !showMaintenanceIndicator;
    }

    if (adminReadEditAction instanceof HTMLButtonElement) {
      const canEditMaintenance = resolvedVehicleStatus === "maintenance";
      adminReadEditAction.hidden = !(
        fleetModeForReadModal === "type" || canEditMaintenance
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-id",
        String(vehicleId > 0 ? vehicleId : ""),
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-type",
        vehicleTypeRaw || "cars",
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-full-name",
        vehicleFullName,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-short-name",
        vehicleName,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-price",
        vehiclePricePerDay,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-driver-age",
        vehicleDriverAge,
      );
      adminReadEditAction.setAttribute("data-edit-vehicle-seats", vehicleSeats);
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-transmission",
        vehicleTransmission,
      );
      adminReadEditAction.setAttribute("data-edit-vehicle-fuel", vehicleFuel);
      const _editMileageAttr = (() => {
        const p = Number.parseFloat(String(vehicleMileage || "").trim());
        return Number.isFinite(p) ? p.toFixed(2) : "";
      })();
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-mileage",
        _editMileageAttr,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-license",
        vehicleLicensePlate,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-license-plate",
        vehicleLicensePlate,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-gps-id",
        vehicleGpsId,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-status",
        resolvedVehicleStatus,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-last-service",
        vehicleLastServiceDate,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-description",
        vehicleDescription,
      );
      adminReadEditAction.setAttribute(
        "data-edit-vehicle-image",
        vehicleImagePath,
      );
      adminReadEditAction.setAttribute(
        "data-delete-fleet-mode",
        fleetModeForReadModal,
      );
      adminReadEditAction.setAttribute(
        "data-delete-fleet-type",
        triggerButton.getAttribute("data-delete-fleet-type") || "cars",
      );
      adminReadEditAction.setAttribute(
        "data-delete-fleet-status",
        triggerButton.getAttribute("data-delete-fleet-status") ||
          resolvedVehicleStatus,
      );
    }

    if (adminReadVehicleImage instanceof HTMLImageElement) {
      adminReadVehicleImage.src = vehicleImagePath;
      adminReadVehicleImage.alt = vehicleName;
    }

    if (adminReadVehicleTypeNode instanceof HTMLElement) {
      adminReadVehicleTypeNode.textContent = toTitleCase(
        vehicleTypeRaw.replace(/s$/, ""),
      );
    }

    if (adminReadVehicleFullNameNode instanceof HTMLElement) {
      adminReadVehicleFullNameNode.textContent = vehicleFullName;
    }

    if (adminReadVehicleSeatsNode instanceof HTMLElement) {
      const seatsValue = normalizeReadValue(
        vehicleSeats,
        resolvedVehicleStatus,
        false,
      );
      adminReadVehicleSeatsNode.textContent =
        seatsValue === readUnavailableText
          ? readUnavailableText
          : `${seatsValue} Seats`;
    }

    if (adminReadVehicleTransmissionNode instanceof HTMLElement) {
      adminReadVehicleTransmissionNode.textContent = toTitleCase(
        normalizeReadValue(vehicleTransmission, resolvedVehicleStatus, false),
      );
    }

    if (adminReadVehicleAgeNode instanceof HTMLElement) {
      const ageValue = normalizeReadValue(
        vehicleDriverAge,
        resolvedVehicleStatus,
        false,
      );
      adminReadVehicleAgeNode.textContent =
        ageValue === readUnavailableText
          ? readUnavailableText
          : `${ageValue}+ Years`;
    }

    if (adminReadVehicleFuelNode instanceof HTMLElement) {
      adminReadVehicleFuelNode.textContent = toTitleCase(
        normalizeReadValue(vehicleFuel, resolvedVehicleStatus, false),
      );
    }

    if (adminReadVehicleMileageNode instanceof HTMLElement) {
      const mileageValue = normalizeReadValue(
        vehicleMileage,
        resolvedVehicleStatus,
        false,
      );
      if (mileageValue === readUnavailableText) {
        adminReadVehicleMileageNode.textContent = readUnavailableText;
      } else {
        const parsed = Number.parseFloat(String(mileageValue).trim());
        if (Number.isFinite(parsed)) {
          adminReadVehicleMileageNode.textContent = `${parsed.toFixed(2)} km/L`;
        } else {
          adminReadVehicleMileageNode.textContent = readUnavailableText;
        }
      }
    }

    if (adminReadVehiclePlateNode instanceof HTMLElement) {
      adminReadVehiclePlateNode.textContent = normalizeReadValue(
        vehicleLicensePlate,
        resolvedVehicleStatus,
        false,
      );
    }

    updateReadStatusRows(resolvedVehicleStatus, details);

    if (adminReadDeleteAction instanceof HTMLElement) {
      const canDeleteFromReadModal =
        fleetModeForReadModal === "type" &&
        resolvedVehicleStatus === "available";
      adminReadDeleteAction.hidden = !canDeleteFromReadModal;
      adminReadDeleteAction.setAttribute(
        "data-delete-vehicle-id",
        String(vehicleId > 0 ? vehicleId : ""),
      );
      adminReadDeleteAction.setAttribute(
        "data-delete-vehicle-label",
        vehicleName,
      );
      adminReadDeleteAction.setAttribute(
        "data-delete-fleet-mode",
        fleetModeForReadModal,
      );
      adminReadDeleteAction.setAttribute(
        "data-delete-fleet-type",
        triggerButton.getAttribute("data-delete-fleet-type") || "cars",
      );
      adminReadDeleteAction.setAttribute(
        "data-delete-fleet-status",
        triggerButton.getAttribute("data-delete-fleet-status") || "reserved",
      );
    }

    readModalContext = {
      vehicleId,
      vehicleStatus: resolvedVehicleStatus,
      fleetMode: fleetModeForReadModal,
      fleetType: triggerButton.getAttribute("data-delete-fleet-type") || "cars",
      fleetStatus:
        triggerButton.getAttribute("data-delete-fleet-status") || "maintenance",
      vehicleType: vehicleTypeRaw,
      vehicleFullName,
      vehicleShortName: vehicleName,
      vehiclePricePerDay,
      vehicleDriverAge,
      vehicleSeats,
      vehicleTransmission,
      vehicleFuel,
      vehicleLicensePlate,
      vehicleGpsId,
      vehicleLastServiceDate,
      vehicleDescription,
      vehicleImagePath,
      maintenanceIssueDescription: details.maintenanceIssueDescription,
      maintenanceWorkshop: details.maintenanceWorkshop,
      maintenanceEstimateDatetime: details.maintenanceEstimateDatetime,
      maintenanceCost: details.maintenanceCost,
    };
  };

  // admin fleet delete: populate delete modal fields with the selected vehicle and active fleet filters.
  const hydrateDeleteVehicleModal = (triggerButton) => {
    if (!(triggerButton instanceof HTMLElement)) {
      return;
    }

    const vehicleId = Number.parseInt(
      triggerButton.getAttribute("data-delete-vehicle-id") || "0",
      10,
    );
    const vehicleName =
      triggerButton.getAttribute("data-delete-vehicle-label") ||
      triggerButton.getAttribute("data-delete-vehicle-name") ||
      "this vehicle";
    const fleetMode =
      triggerButton.getAttribute("data-delete-fleet-mode") || "type";
    const fleetType =
      triggerButton.getAttribute("data-delete-fleet-type") || "cars";
    const fleetStatus =
      triggerButton.getAttribute("data-delete-fleet-status") || "reserved";

    if (adminDeleteVehicleIdInput instanceof HTMLInputElement) {
      adminDeleteVehicleIdInput.value =
        Number.isFinite(vehicleId) && vehicleId > 0 ? String(vehicleId) : "";
    }

    if (adminDeleteVehicleNameNode instanceof HTMLElement) {
      adminDeleteVehicleNameNode.textContent = vehicleName;
    }

    if (adminDeleteFleetModeInput instanceof HTMLInputElement) {
      adminDeleteFleetModeInput.value = fleetMode;
    }

    if (adminDeleteFleetTypeInput instanceof HTMLInputElement) {
      adminDeleteFleetTypeInput.value = fleetType;
    }

    if (adminDeleteFleetStatusInput instanceof HTMLInputElement) {
      adminDeleteFleetStatusInput.value = fleetStatus;
    }
  };

  const setModalState = (modal, isOpen) => {
    modal.hidden = !isOpen;
    modal.setAttribute("aria-hidden", isOpen ? "false" : "true");
    modal.classList.toggle("is-open", isOpen);
  };

  const focusFirstModalControl = (modal) => {
    window.requestAnimationFrame(() => {
      const focusTarget =
        modal.querySelector(".menu-modal__close") ||
        modal.querySelector(".menu-modal__dialog");
      if (focusTarget instanceof HTMLElement) {
        focusTarget.focus({ preventScroll: true });
      }
    });
  };

  const showModal = (modalId, pushHistory = true) => {
    const targetModal = modalById.get(modalId);
    if (!targetModal) {
      return;
    }

    const activeModalId =
      modalHistory.length > 0 ? modalHistory[modalHistory.length - 1] : null;
    if (activeModalId) {
      const activeModal = modalById.get(activeModalId);
      if (activeModal) {
        setModalState(activeModal, false);
      }
    }

    setModalState(targetModal, true);
    document.body.classList.add("menu-modal-open");

    if (pushHistory) {
      modalHistory.push(modalId);
    }

    focusFirstModalControl(targetModal);
  };

  const closeAllModals = () => {
    modalHistory.length = 0;
    modalById.forEach((modal) => {
      setModalState(modal, false);
    });
    document.body.classList.remove("menu-modal-open");
    closeCreateCustomSelect();
    clearAdminLoginUiState();
    resetCreateVehicleModal();
    resetEditVehicleModal();
    resetBookingReadModal();

    if (lastFocusedElement instanceof HTMLElement) {
      lastFocusedElement.focus({ preventScroll: true });
    }
  };

  const goBack = () => {
    if (modalHistory.length > 1) {
      const currentModalId = modalHistory.pop();
      const previousModalId = modalHistory[modalHistory.length - 1];
      const currentModal = modalById.get(currentModalId);
      const previousModal = modalById.get(previousModalId);

      if (currentModalId === "admin-login-modal") {
        clearAdminLoginUiState();
      }

      if (currentModal) {
        setModalState(currentModal, false);
      }

      if (previousModal) {
        setModalState(previousModal, true);
        focusFirstModalControl(previousModal);
      }

      return;
    }

    const hasSafeHistory =
      window.history.length > 1 &&
      typeof document.referrer === "string" &&
      document.referrer.startsWith(window.location.origin);

    closeAllModals();

    if (hasSafeHistory) {
      window.history.back();
    }
  };

  menuOpenButtons.forEach((button) => {
    button.addEventListener("click", () => {
      lastFocusedElement =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;
      modalHistory.length = 0;
      showModal("menu-modal", true);
    });
  });

  targetButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const targetId = button.getAttribute("data-modal-target");
      if (!targetId) {
        return;
      }

      if (targetId === "admin-vehicle-read-modal") {
        hydrateReadVehicleModal(button);
      }

      if (targetId === "admin-delete-vehicle-modal") {
        hydrateDeleteVehicleModal(button);
      }

      if (targetId === "admin-booking-read-modal") {
        hydrateBookingReadModal(button);
      }

      if (targetId === "admin-booking-track-modal") {
        hydrateBookingTrackModal(button);
      }

      if (targetId === "admin-delete-booking-modal") {
        hydrateDeleteBookingModal(button);
      }

      if (targetId === "admin-maintenance-fill-modal") {
        hydrateMaintenanceFillModal(button);
      }

      if (targetId === "admin-maintenance-edit-modal") {
        hydrateMaintenanceEditModal(button);
      }

      if (targetId === "admin-create-vehicle-modal") {
        hydrateCreateVehicleModal(button);
      }

      if (targetId === "admin-edit-vehicle-modal") {
        hydrateEditVehicleModal(button);
      }

      if (modalHistory.length === 0) {
        lastFocusedElement =
          document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
      }

      showModal(targetId, true);
    });
  });

  closeButtons.forEach((button) => {
    button.addEventListener("click", closeAllModals);
  });

  backButtons.forEach((button) => {
    button.addEventListener("click", goBack);
  });

  if (
    adminCreateVehicleTypeInput instanceof HTMLSelectElement &&
    adminCreateFleetTypeInput instanceof HTMLInputElement
  ) {
    adminCreateVehicleTypeInput.addEventListener("change", () => {
      adminCreateFleetTypeInput.value = adminCreateVehicleTypeInput.value;
    });
  }

  if (
    adminEditVehicleTypeInput instanceof HTMLSelectElement &&
    adminEditFleetTypeInput instanceof HTMLInputElement
  ) {
    adminEditVehicleTypeInput.addEventListener("change", () => {
      adminEditFleetTypeInput.value = adminEditVehicleTypeInput.value;
    });
  }

  if (adminCreateImageFieldsRoot instanceof HTMLElement) {
    adminCreateImageFieldsRoot.addEventListener("change", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLInputElement)) {
        return;
      }

      if (!target.matches("[data-image-file-input]")) {
        return;
      }

      updateCreateImageUi();
    });

    adminCreateImageFieldsRoot.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const addButton = target.closest("[data-image-add-button]");
      if (addButton instanceof HTMLElement) {
        event.preventDefault();
        appendImageFieldRow(adminCreateImageFieldsRoot);
        updateCreateImageUi();
        return;
      }

      const clearButton = target.closest("[data-image-clear-button]");
      if (!(clearButton instanceof HTMLElement)) {
        return;
      }

      event.preventDefault();
      const rowNode = clearButton.closest("[data-image-field-row]");
      const rowInput = getImageInputFromRow(rowNode);

      // Context-aware behavior: if file selected, clear it; if empty, remove the field
      if (rowInput instanceof HTMLInputElement) {
        const hasFile = rowInput.files && rowInput.files.length > 0;

        if (hasFile) {
          // Clear the file but keep the field
          rowInput.value = "";
          updateCreateImageUi();
        } else {
          // Remove the entire field (only if not the last/only field with required attribute)
          const rows = getImageFieldRows(adminCreateImageFieldsRoot);
          if (rows.length > 1) {
            removeImageFieldRow(rowNode, adminCreateImageFieldsRoot);
            updateCreateImageUi();
          }
        }
      }
    });
  }

  if (adminEditImageFieldsRoot instanceof HTMLElement) {
    adminEditImageFieldsRoot.addEventListener("change", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLInputElement)) {
        return;
      }

      if (!target.matches("[data-image-file-input]")) {
        return;
      }

      const rowNode = target.closest("[data-image-field-row]");
      if (rowNode instanceof HTMLElement) {
        rowNode.dataset.imageExisting = "false";
      }

      if (adminEditRemoveCurrentImageInput instanceof HTMLInputElement) {
        adminEditRemoveCurrentImageInput.value = "0";
      }

      updateEditImageUi();
    });

    adminEditImageFieldsRoot.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const addButton = target.closest("[data-image-add-button]");
      if (addButton instanceof HTMLElement) {
        event.preventDefault();
        appendImageFieldRow(adminEditImageFieldsRoot);
        updateEditImageUi();
        return;
      }

      const clearButton = target.closest("[data-image-clear-button]");
      if (!(clearButton instanceof HTMLElement)) {
        return;
      }

      event.preventDefault();
      const rowNode = clearButton.closest("[data-image-field-row]");
      const rowInput = getImageInputFromRow(rowNode);

      // Context-aware behavior: if file selected, clear it; if empty, remove the field
      if (rowInput instanceof HTMLInputElement) {
        const hasFile = rowInput.files && rowInput.files.length > 0;
        const isExistingPrimary =
          rowNode instanceof HTMLElement &&
          rowNode.dataset.imageExisting === "true";

        if (hasFile) {
          // Clear the file but keep the field
          rowInput.value = "";
          if (rowNode instanceof HTMLElement) {
            rowNode.dataset.imageExisting = "false";
          }
          updateEditImageUi();
        } else if (isExistingPrimary) {
          if (adminEditCurrentImagePathInput instanceof HTMLInputElement) {
            adminEditCurrentImagePathInput.value = "";
          }

          if (adminEditRemoveCurrentImageInput instanceof HTMLInputElement) {
            adminEditRemoveCurrentImageInput.value = "1";
          }

          if (rowNode instanceof HTMLElement) {
            rowNode.dataset.imageExisting = "false";
          }

          const rowNameInput = getImageNameInputFromRow(rowNode);
          if (rowNameInput instanceof HTMLInputElement) {
            rowNameInput.value = "";
          }

          updateEditImageUi();
        } else {
          // Remove the entire field (only if not the only field)
          const rows = getImageFieldRows(adminEditImageFieldsRoot);
          if (rows.length > 1) {
            removeImageFieldRow(rowNode, adminEditImageFieldsRoot);
            updateEditImageUi();
          }
        }
      }
    });
  }

  initAdminBookingReturnTimePicker();

  if (adminBookingReturnTimeInput instanceof HTMLInputElement) {
    const onBookingReturnTimeChanged = () => {
      validateAdminBookingReturnTime(false);
      updateBookingLateFeePreview();
    };

    adminBookingReturnTimeInput.addEventListener(
      "input",
      onBookingReturnTimeChanged,
    );
    adminBookingReturnTimeInput.addEventListener(
      "change",
      onBookingReturnTimeChanged,
    );
  }

  if (adminBookingCompleteForm instanceof HTMLFormElement) {
    adminBookingCompleteForm.addEventListener("submit", (event) => {
      if (validateAdminBookingReturnTime(true)) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    });
  }

  if (adminBookingTrackAction instanceof HTMLElement) {
    adminBookingTrackAction.addEventListener("click", (event) => {
      if (adminBookingTrackAction.classList.contains("is-disabled")) {
        event.preventDefault();
        event.stopPropagation();
      }
    });
  }

  if (adminBookingReturnTimeInput instanceof HTMLInputElement) {
    adminBookingReturnTimePickerButtons.forEach((pickerButton) => {
      if (!(pickerButton instanceof HTMLButtonElement)) {
        return;
      }

      pickerButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (adminBookingReturnTimeInput._flatpickr) {
          adminBookingReturnTimeInput._flatpickr.open();
          return;
        }

        adminBookingReturnTimeInput.focus({ preventScroll: true });
        if (typeof adminBookingReturnTimeInput.showPicker === "function") {
          try {
            adminBookingReturnTimeInput.showPicker();
            return;
          } catch (error) {}
        }

        adminBookingReturnTimeInput.click();
      });
    });
  }

  if (adminCreateVehicleForm instanceof HTMLFormElement) {
    adminCreateVehicleForm.addEventListener("submit", (event) => {
      if (!validateCreateVehicleStep(createVehicleStep)) {
        event.preventDefault();
        event.stopPropagation();
      }
    });
  }

  if (adminEditVehicleForm instanceof HTMLFormElement) {
    adminEditVehicleForm.addEventListener("submit", (event) => {
      if (!validateEditVehicleStep(editVehicleStep)) {
        event.preventDefault();
        event.stopPropagation();
      }
    });
  }

  if (adminMaintenanceFillForm instanceof HTMLFormElement) {
    adminMaintenanceFillForm.addEventListener("submit", (event) => {
      const isEstimateValid = validateDateNotBeforeToday(
        adminMaintenanceFillEstimateInput,
        "Please enter a valid estimated completion date.",
        "Estimated completion date cannot be earlier than today.",
        true,
      );
      if (isEstimateValid) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    });
  }

  if (adminMaintenanceEditForm instanceof HTMLFormElement) {
    adminMaintenanceEditForm.addEventListener("submit", (event) => {
      const isEstimateValid = validateDateNotBeforeToday(
        adminMaintenanceEditEstimateInput,
        "Please enter a valid estimated completion date.",
        "Estimated completion date cannot be earlier than today.",
        true,
      );
      if (isEstimateValid) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    });
  }

  [
    adminCreateLastServiceInput,
    adminEditLastServiceInput,
    adminMaintenanceFillEstimateInput,
    adminMaintenanceEditEstimateInput,
  ].forEach((inputNode) => {
    if (!(inputNode instanceof HTMLInputElement)) {
      return;
    }

    inputNode.addEventListener("input", () => {
      inputNode.setCustomValidity("");
    });

    inputNode.addEventListener("change", () => {
      inputNode.setCustomValidity("");
    });
  });

  adminCreateStepNextButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (!validateCreateVehicleStep(createVehicleStep)) {
        return;
      }

      setCreateVehicleStep(createVehicleStep + 1);
    });
  });

  adminCreateStepPrevButtons.forEach((button) => {
    button.addEventListener("click", () => {
      setCreateVehicleStep(createVehicleStep - 1);
    });
  });

  adminEditStepNextButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (!validateEditVehicleStep(editVehicleStep)) {
        return;
      }

      setEditVehicleStep(editVehicleStep + 1);
    });
  });

  adminEditStepPrevButtons.forEach((button) => {
    button.addEventListener("click", () => {
      setEditVehicleStep(editVehicleStep - 1);
    });
  });

  if (adminCreateStepCloseButton instanceof HTMLElement) {
    adminCreateStepCloseButton.addEventListener("click", closeAllModals);
  }

  if (adminEditStepCloseButton instanceof HTMLElement) {
    adminEditStepCloseButton.addEventListener("click", closeAllModals);
  }

  navigationLinks.forEach((link) => {
    link.addEventListener("click", () => {
      closeAllModals();
    });
  });

  passwordToggleButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const targetId = button.getAttribute("data-password-target");
      if (!targetId) {
        return;
      }

      const targetInput = document.getElementById(targetId);
      if (!(targetInput instanceof HTMLInputElement)) {
        return;
      }

      const shouldShow = targetInput.type === "password";
      targetInput.type = shouldShow ? "text" : "password";
      button.setAttribute(
        "aria-label",
        shouldShow ? "Hide password" : "Show password",
      );

      const icon = button.querySelector(".material-symbols-rounded");
      if (icon) {
        icon.textContent = shouldShow ? "visibility_off" : "visibility";
      }
    });
  });

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (
      openCreateCustomSelectController &&
      !openCreateCustomSelectController.shell.contains(target)
    ) {
      closeCreateCustomSelect(openCreateCustomSelectController);
    }

    if (target.classList.contains("menu-modal__overlay")) {
      closeAllModals();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (openCreateCustomSelectController) {
        closeCreateCustomSelect(openCreateCustomSelectController);
        return;
      }

      closeAllModals();
    }
  });

  const autoOpenAdminModal =
    document
      .getElementById("admin-login-modal")
      ?.getAttribute("data-open-on-load") === "true";
  if (autoOpenAdminModal) {
    modalHistory.length = 0;
    showModal("menu-modal", true);
    showModal("admin-login-modal", true);
  } else {
    clearAdminLoginUiState();
  }

  window.addEventListener("pageshow", () => {
    if (!autoOpenAdminModal) {
      clearAdminLoginUiState();
    }
  });

  // maintenance edit/fillup form: reopen read modal after maintenance actions when backend provides auto-open vehicle context.
  const autoOpenReadTrigger = document.querySelector(
    "[data-modal-target='admin-vehicle-read-modal'][data-auto-open-read='true']",
  );
  if (autoOpenReadTrigger instanceof HTMLElement) {
    lastFocusedElement = autoOpenReadTrigger;
    hydrateReadVehicleModal(autoOpenReadTrigger);
    showModal("admin-vehicle-read-modal", true);
  }

  const autoOpenBookingTrigger = document.querySelector(
    "[data-modal-target='admin-booking-read-modal'][data-auto-open-booking='true']",
  );
  if (autoOpenBookingTrigger instanceof HTMLElement) {
    lastFocusedElement = autoOpenBookingTrigger;
    hydrateBookingReadModal(autoOpenBookingTrigger);
    showModal("admin-booking-read-modal", true);
  }
})();
