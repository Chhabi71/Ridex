/**
 * Purpose: Frontend logic for booking form and confirmation flow.
 */

(function () {
  const DATE_INPUT_IDS = ["pickup-date", "return-date"];
  const TIME_INPUT_IDS = ["pickup-time", "return-time"];
  const MAINTENANCE_DATE_INPUT_IDS = [
    "maintenance-fill-estimate",
    "maintenance-edit-estimate",
  ];
  const CREATE_VEHICLE_DATE_INPUT_IDS = [
    "create-vehicle-last-service-date",
    "edit-vehicle-last-service-date",
  ];
  const USER_REGISTER_DATE_INPUT_IDS = ["user-register-date-of-birth"];

  const getInputById = (id) => document.getElementById(id);

  const getTodayIsoDate = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  const openPickerForInput = (input) => {
    if (!input) {
      return;
    }

    if (input._flatpickr) {
      input._flatpickr.open();
      return;
    }

    input.focus({ preventScroll: true });

    if (typeof input.showPicker === "function") {
      try {
        input.showPicker();
      } catch (error) {}
      return;
    }

    input.click();
  };

  const bindPickerTriggers = () => {
    document.querySelectorAll("[data-open-picker-for]").forEach((trigger) => {
      const targetId = trigger.getAttribute("data-open-picker-for");

      if (targetId === "admin-booking-return-time-input") {
        return;
      }

      const input = targetId ? getInputById(targetId) : null;

      if (!input) {
        return;
      }

      trigger.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        openPickerForInput(input);
      });
    });
  };

  const initVehicleTypeTabs = () => {
    const vehicleTypeInput = getInputById("vehicle-type-input");
    const tabs = Array.from(
      document.querySelectorAll(
        ".booking-engine__tabs .booking-tab[data-vehicle-type]",
      ),
    );

    if (!vehicleTypeInput || tabs.length === 0) {
      return;
    }

    const setActiveTab = (vehicleType) => {
      const normalizedType = String(vehicleType || "").toLowerCase();
      let matchedType = "";

      tabs.forEach((tab, index) => {
        const tabType = String(tab.dataset.vehicleType || "").toLowerCase();
        const isActive = tabType === normalizedType;

        if (isActive) {
          matchedType = tabType;
        }

        tab.classList.toggle("is-active", isActive);
        tab.setAttribute("aria-selected", isActive ? "true" : "false");
        tab.setAttribute("tabindex", isActive || index === 0 ? "0" : "-1");
      });

      vehicleTypeInput.value =
        matchedType || String(tabs[0].dataset.vehicleType || "cars");
    };

    const initialType =
      vehicleTypeInput.value ||
      tabs.find((tab) => tab.classList.contains("is-active"))?.dataset
        .vehicleType ||
      tabs[0].dataset.vehicleType;

    setActiveTab(initialType);

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        setActiveTab(tab.dataset.vehicleType);
      });
    });
  };

  const initHeroBookNowAction = () => {
    const heroBookNowButton = document.querySelector(".hero-banner__cta");
    const bookingEngine = document.querySelector(".booking-engine");
    const pickupLocationInput = getInputById("pickup-location");

    if (!heroBookNowButton || !bookingEngine) {
      return;
    }

    heroBookNowButton.addEventListener("click", () => {
      bookingEngine.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });

      bookingEngine.classList.remove("booking-engine--pop");
      void bookingEngine.offsetWidth;
      bookingEngine.classList.add("booking-engine--pop");

      window.setTimeout(() => {
        bookingEngine.classList.remove("booking-engine--pop");

        if (pickupLocationInput) {
          pickupLocationInput.focus({ preventScroll: true });
        }
      }, 480);
    });
  };

  const initSameReturnLocationSync = () => {
    const sameReturnCheckbox = getInputById("same-return");
    const pickupLocationInput = getInputById("pickup-location");
    const returnLocationInput = getInputById("return-location");

    if (!sameReturnCheckbox || !pickupLocationInput || !returnLocationInput) {
      return;
    }

    const syncReturnLocation = () => {
      if (!sameReturnCheckbox.checked) {
        return;
      }

      returnLocationInput.value = pickupLocationInput.value;
    };

    sameReturnCheckbox.addEventListener("change", () => {
      syncReturnLocation();
    });

    pickupLocationInput.addEventListener("input", () => {
      syncReturnLocation();
    });

    pickupLocationInput.addEventListener("change", () => {
      syncReturnLocation();
    });

    syncReturnLocation();
  };

  const parseBookingDateTime = (dateValue, timeValue) => {
    const normalizedDate = String(dateValue || "").trim();
    const normalizedTime = String(timeValue || "").trim();

    const dateMatch = /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/.exec(
      normalizedDate,
    );
    if (!dateMatch) {
      return null;
    }

    const day = Number.parseInt(dateMatch[1], 10);
    const month = Number.parseInt(dateMatch[2], 10);
    const year = Number.parseInt(dateMatch[3], 10);
    if (
      !Number.isFinite(day) ||
      !Number.isFinite(month) ||
      !Number.isFinite(year)
    ) {
      return null;
    }

    let hours = 0;
    let minutes = 0;
    const amPmMatch = /^(\d{1,2}):(\d{2})\s*([AaPp][Mm])$/.exec(normalizedTime);
    const plainTimeMatch = /^(\d{1,2}):(\d{2})$/.exec(normalizedTime);

    if (amPmMatch) {
      hours = Number.parseInt(amPmMatch[1], 10);
      minutes = Number.parseInt(amPmMatch[2], 10);
      const meridiem = String(amPmMatch[3]).toUpperCase();

      if (hours === 12) {
        hours = 0;
      }

      if (meridiem === "PM") {
        hours += 12;
      }
    } else if (plainTimeMatch) {
      hours = Number.parseInt(plainTimeMatch[1], 10);
      minutes = Number.parseInt(plainTimeMatch[2], 10);
    } else {
      return null;
    }

    if (
      !Number.isFinite(hours) ||
      !Number.isFinite(minutes) ||
      hours < 0 ||
      hours > 23 ||
      minutes < 0 ||
      minutes > 59
    ) {
      return null;
    }

    const parsed = new Date(year, month - 1, day, hours, minutes, 0, 0);
    if (
      parsed.getFullYear() !== year ||
      parsed.getMonth() !== month - 1 ||
      parsed.getDate() !== day
    ) {
      return null;
    }

    return parsed;
  };

  const setInputWrapperState = (inputNode, stateName) => {
    if (!(inputNode instanceof HTMLElement)) {
      return;
    }

    const wrapper = inputNode.closest(".booking-input");
    if (wrapper instanceof HTMLElement) {
      wrapper.setAttribute("data-state", stateName);
    }
  };

  const isUserAuthenticated = () => {
    const bodyNode = document.body;
    if (!(bodyNode instanceof HTMLBodyElement)) {
      return false;
    }

    return bodyNode.getAttribute("data-user-authenticated") === "1";
  };

  const syncPostAuthRedirectInputs = (redirectUrl) => {
    const normalizedRedirect = String(redirectUrl || "").trim() || "index.php";
    const redirectInputs = Array.from(
      document.querySelectorAll("[data-user-post-auth-redirect]"),
    );

    redirectInputs.forEach((inputNode) => {
      if (inputNode instanceof HTMLInputElement) {
        inputNode.value = normalizedRedirect;
      }
    });
  };

  const openUserLoginModal = (redirectUrl) => {
    syncPostAuthRedirectInputs(redirectUrl);

    const loginTrigger = document.querySelector(
      "[data-booking-login-modal-trigger='true']",
    );
    if (!(loginTrigger instanceof HTMLElement)) {
      return;
    }

    loginTrigger.click();
  };

  const setQueryValueIfPresent = (params, key, value) => {
    const normalizedValue = String(value || "").trim();
    if (normalizedValue === "") {
      return;
    }

    params.set(key, normalizedValue);
  };

  const buildBookingRedirectFromForm = (formNode) => {
    if (!(formNode instanceof HTMLFormElement)) {
      return "index.php";
    }

    const pageValue = String(
      formNode.querySelector("input[name='page']")?.value || "",
    )
      .trim()
      .toLowerCase();
    if (
      !["booking-select", "booking-engine", "booking-checkout"].includes(
        pageValue,
      )
    ) {
      return "index.php";
    }

    const params = new URLSearchParams();
    params.set("page", pageValue);

    const vehicleTypeValue =
      formNode.querySelector("input[name='vehicle_type']")?.value || "cars";
    setQueryValueIfPresent(params, "vehicle_type", vehicleTypeValue);

    if (pageValue !== "booking-select") {
      const vehicleIdValue =
        formNode.querySelector("input[name='vehicle_id']")?.value || "";
      setQueryValueIfPresent(params, "vehicle_id", vehicleIdValue);
    }

    [
      "pickup-location",
      "return-location",
      "pickup-date",
      "return-date",
      "pickup-time",
      "return-time",
    ].forEach((fieldName) => {
      const fieldNode = formNode.querySelector(`input[name='${fieldName}']`);
      setQueryValueIfPresent(params, fieldName, fieldNode?.value || "");
    });

    const sameReturnNode = formNode.querySelector("input[name='same-return']");
    if (sameReturnNode instanceof HTMLInputElement && sameReturnNode.checked) {
      params.set("same-return", "1");
    }

    const attemptValue =
      formNode.querySelector("input[name='attempt']")?.value || "";
    if (attemptValue === "1") {
      params.set("attempt", "1");
    }

    params.set("flow_start", "1");
    return `index.php?${params.toString()}`;
  };

  const buildBookingRedirectFromBookNowButton = (buttonNode) => {
    if (!(buttonNode instanceof HTMLElement)) {
      return "index.php";
    }

    const params = new URLSearchParams();
    params.set("page", "booking-engine");

    setQueryValueIfPresent(
      params,
      "vehicle_id",
      buttonNode.getAttribute("data-book-vehicle-id") || "",
    );
    setQueryValueIfPresent(
      params,
      "vehicle_type",
      buttonNode.getAttribute("data-book-vehicle-type") || "cars",
    );
    setQueryValueIfPresent(
      params,
      "pickup-location",
      buttonNode.getAttribute("data-book-pickup-location") || "",
    );
    setQueryValueIfPresent(
      params,
      "return-location",
      buttonNode.getAttribute("data-book-return-location") || "",
    );
    setQueryValueIfPresent(
      params,
      "pickup-date",
      buttonNode.getAttribute("data-book-pickup-date") || "",
    );
    setQueryValueIfPresent(
      params,
      "return-date",
      buttonNode.getAttribute("data-book-return-date") || "",
    );
    setQueryValueIfPresent(
      params,
      "pickup-time",
      buttonNode.getAttribute("data-book-pickup-time") || "",
    );
    setQueryValueIfPresent(
      params,
      "return-time",
      buttonNode.getAttribute("data-book-return-time") || "",
    );

    params.set("flow_start", "1");
    return `index.php?${params.toString()}`;
  };

  const initBookingFormValidation = () => {
    const bookingForms = Array.from(
      document.querySelectorAll(
        ".booking-engine__form[data-booking-flow-form]",
      ),
    );
    if (!bookingForms.length) {
      return;
    }

    const validateBookingForm = (formNode) => {
      if (!(formNode instanceof HTMLFormElement)) {
        return false;
      }

      const pickupLocationInput = formNode.querySelector(
        "input[name='pickup-location']",
      );
      const returnLocationInput = formNode.querySelector(
        "input[name='return-location']",
      );
      const pickupDateInput = formNode.querySelector(
        "input[name='pickup-date']",
      );
      const returnDateInput = formNode.querySelector(
        "input[name='return-date']",
      );
      const pickupTimeInput = formNode.querySelector(
        "input[name='pickup-time']",
      );
      const returnTimeInput = formNode.querySelector(
        "input[name='return-time']",
      );
      const sameReturnCheckbox = formNode.querySelector(
        "input[name='same-return']",
      );
      const returnLocationHelp = formNode.querySelector(
        "#return-location-help",
      );
      const returnDateHelp = formNode.querySelector("#return-date-help");

      const allInputs = [
        pickupLocationInput,
        returnLocationInput,
        pickupDateInput,
        returnDateInput,
        pickupTimeInput,
        returnTimeInput,
      ];

      allInputs.forEach((inputNode) => {
        if (inputNode instanceof HTMLInputElement) {
          inputNode.setCustomValidity("");
          setInputWrapperState(inputNode, "default");
        }
      });

      if (returnLocationHelp instanceof HTMLElement) {
        returnLocationHelp.textContent = "";
        returnLocationHelp.classList.remove("booking-field__help--error");
      }

      if (returnDateHelp instanceof HTMLElement) {
        returnDateHelp.textContent = "";
        returnDateHelp.classList.remove("booking-field__help--error");
      }

      if (
        sameReturnCheckbox instanceof HTMLInputElement &&
        sameReturnCheckbox.checked &&
        pickupLocationInput instanceof HTMLInputElement &&
        returnLocationInput instanceof HTMLInputElement
      ) {
        returnLocationInput.value = pickupLocationInput.value;
      }

      const requiredChecks = [
        {
          node: pickupLocationInput,
          message: "Please enter pickup location.",
        },
        {
          node: returnLocationInput,
          message: "Please enter return location.",
        },
        {
          node: pickupDateInput,
          message: "Please select pickup date.",
        },
        {
          node: pickupTimeInput,
          message: "Please select pickup time.",
        },
        {
          node: returnDateInput,
          message: "Please select return date.",
        },
        {
          node: returnTimeInput,
          message: "Please select return time.",
        },
      ];

      for (let index = 0; index < requiredChecks.length; index += 1) {
        const check = requiredChecks[index];
        if (!(check.node instanceof HTMLInputElement)) {
          continue;
        }

        if (check.node.value.trim() === "") {
          check.node.setCustomValidity(check.message);
          setInputWrapperState(check.node, "error");
          check.node.reportValidity();

          if (
            check.node === returnLocationInput &&
            returnLocationHelp instanceof HTMLElement
          ) {
            returnLocationHelp.textContent = check.message;
            returnLocationHelp.classList.add("booking-field__help--error");
          }

          return false;
        }
      }

      if (
        pickupDateInput instanceof HTMLInputElement &&
        pickupTimeInput instanceof HTMLInputElement &&
        returnDateInput instanceof HTMLInputElement &&
        returnTimeInput instanceof HTMLInputElement
      ) {
        const pickupDateTime = parseBookingDateTime(
          pickupDateInput.value,
          pickupTimeInput.value,
        );
        const returnDateTime = parseBookingDateTime(
          returnDateInput.value,
          returnTimeInput.value,
        );

        if (!pickupDateTime || !returnDateTime) {
          const invalidMessage =
            "Please select valid pickup and return date/time.";
          returnDateInput.setCustomValidity(invalidMessage);
          setInputWrapperState(returnDateInput, "error");
          if (returnDateHelp instanceof HTMLElement) {
            returnDateHelp.textContent = invalidMessage;
            returnDateHelp.classList.add("booking-field__help--error");
          }
          returnDateInput.reportValidity();
          return false;
        }

        const nowDateTime = new Date();
        if (pickupDateTime <= nowDateTime) {
          const pickupFutureMessage = "Pickup date/time must be in the future.";
          pickupDateInput.setCustomValidity(pickupFutureMessage);
          setInputWrapperState(pickupDateInput, "error");
          setInputWrapperState(pickupTimeInput, "error");
          pickupDateInput.reportValidity();
          return false;
        }

        if (returnDateTime <= nowDateTime) {
          const returnFutureMessage = "Return date/time must be in the future.";
          returnDateInput.setCustomValidity(returnFutureMessage);
          setInputWrapperState(returnDateInput, "error");
          setInputWrapperState(returnTimeInput, "error");
          if (returnDateHelp instanceof HTMLElement) {
            returnDateHelp.textContent = returnFutureMessage;
            returnDateHelp.classList.add("booking-field__help--error");
          }
          returnDateInput.reportValidity();
          return false;
        }

        if (returnDateTime <= pickupDateTime) {
          const orderMessage =
            "Return date/time must be after pickup date/time.";
          returnDateInput.setCustomValidity(orderMessage);
          setInputWrapperState(returnDateInput, "error");
          setInputWrapperState(returnTimeInput, "error");
          if (returnDateHelp instanceof HTMLElement) {
            returnDateHelp.textContent = orderMessage;
            returnDateHelp.classList.add("booking-field__help--error");
          }
          returnDateInput.reportValidity();
          return false;
        }

        const maxReturnDateTime = new Date(pickupDateTime.getTime());
        maxReturnDateTime.setMonth(maxReturnDateTime.getMonth() + 1);
        if (returnDateTime > maxReturnDateTime) {
          const rangeMessage = "Booking duration cannot exceed 1 month.";
          returnDateInput.setCustomValidity(rangeMessage);
          setInputWrapperState(returnDateInput, "error");
          setInputWrapperState(returnTimeInput, "error");
          if (returnDateHelp instanceof HTMLElement) {
            returnDateHelp.textContent = rangeMessage;
            returnDateHelp.classList.add("booking-field__help--error");
          }
          returnDateInput.reportValidity();
          return false;
        }
      }

      return true;
    };

    bookingForms.forEach((bookingForm) => {
      const submitTrigger = bookingForm.querySelector(
        "[data-booking-search-trigger]",
      );

      bookingForm.addEventListener("submit", (event) => {
        if (!isUserAuthenticated()) {
          event.preventDefault();
          event.stopPropagation();
          openUserLoginModal(buildBookingRedirectFromForm(bookingForm));
          return;
        }

        const isValid = validateBookingForm(bookingForm);
        if (isValid) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
      });

      if (!(submitTrigger instanceof HTMLElement)) {
        return;
      }

      submitTrigger.addEventListener("click", (event) => {
        if (!isUserAuthenticated()) {
          event.preventDefault();
          event.stopPropagation();
          openUserLoginModal(buildBookingRedirectFromForm(bookingForm));
          return;
        }

        const isValid = validateBookingForm(bookingForm);
        if (!isValid) {
          return;
        }

        if (typeof bookingForm.requestSubmit === "function") {
          bookingForm.requestSubmit();
          return;
        }

        bookingForm.submit();
      });
    });
  };

  const initBookNowConfirmationContext = () => {
    const bookNowButtons = Array.from(
      document.querySelectorAll(
        "[data-book-now-trigger][data-modal-target='user-booking-confirm-modal']",
      ),
    );
    if (!bookNowButtons.length) {
      return;
    }

    const confirmVehicleIdInput = document.querySelector(
      "[data-booking-confirm-vehicle-id]",
    );
    const confirmVehicleTypeInput = document.querySelector(
      "[data-booking-confirm-vehicle-type]",
    );
    const confirmPickupLocationInput = document.querySelector(
      "[data-booking-confirm-pickup-location]",
    );
    const confirmReturnLocationInput = document.querySelector(
      "[data-booking-confirm-return-location]",
    );
    const confirmPickupDateInput = document.querySelector(
      "[data-booking-confirm-pickup-date]",
    );
    const confirmReturnDateInput = document.querySelector(
      "[data-booking-confirm-return-date]",
    );
    const confirmPickupTimeInput = document.querySelector(
      "[data-booking-confirm-pickup-time]",
    );
    const confirmReturnTimeInput = document.querySelector(
      "[data-booking-confirm-return-time]",
    );

    if (
      !(confirmVehicleIdInput instanceof HTMLInputElement) ||
      !(confirmVehicleTypeInput instanceof HTMLInputElement)
    ) {
      return;
    }

    bookNowButtons.forEach((bookNowButton) => {
      bookNowButton.addEventListener(
        "click",
        (event) => {
          if (isUserAuthenticated()) {
            return;
          }

          event.preventDefault();
          event.stopPropagation();
          openUserLoginModal(
            buildBookingRedirectFromBookNowButton(bookNowButton),
          );
        },
        true,
      );

      bookNowButton.addEventListener("click", () => {
        if (!isUserAuthenticated()) {
          return;
        }

        const setValue = (inputNode, value) => {
          if (inputNode instanceof HTMLInputElement) {
            inputNode.value = String(value || "").trim();
          }
        };

        setValue(
          confirmVehicleIdInput,
          bookNowButton.getAttribute("data-book-vehicle-id") || "0",
        );
        setValue(
          confirmVehicleTypeInput,
          bookNowButton.getAttribute("data-book-vehicle-type") || "cars",
        );
        setValue(
          confirmPickupLocationInput,
          bookNowButton.getAttribute("data-book-pickup-location") || "",
        );
        setValue(
          confirmReturnLocationInput,
          bookNowButton.getAttribute("data-book-return-location") || "",
        );
        setValue(
          confirmPickupDateInput,
          bookNowButton.getAttribute("data-book-pickup-date") || "",
        );
        setValue(
          confirmReturnDateInput,
          bookNowButton.getAttribute("data-book-return-date") || "",
        );
        setValue(
          confirmPickupTimeInput,
          bookNowButton.getAttribute("data-book-pickup-time") || "",
        );
        setValue(
          confirmReturnTimeInput,
          bookNowButton.getAttribute("data-book-return-time") || "",
        );
      });
    });
  };

  const initBookingSeatSlider = () => {
    const seatsSlider = document.querySelector("[data-seats-slider]");
    const seatsSliderValue = document.querySelector(
      "[data-seats-slider-value]",
    );

    if (
      !(seatsSlider instanceof HTMLInputElement) ||
      !(seatsSliderValue instanceof HTMLElement)
    ) {
      return;
    }

    const syncSeatValue = () => {
      const sliderValue = Number.parseInt(seatsSlider.value, 10);
      const sliderMin = Number.parseInt(seatsSlider.min || "0", 10);
      const sliderMax = Number.parseInt(seatsSlider.max || "8", 10);
      const normalizedMin = Number.isFinite(sliderMin) ? sliderMin : 0;
      const normalizedMax = Number.isFinite(sliderMax) ? sliderMax : 8;
      const safeRange = Math.max(1, normalizedMax - normalizedMin);
      const safeValue = Number.isFinite(sliderValue)
        ? Math.min(normalizedMax, Math.max(normalizedMin, sliderValue))
        : normalizedMin;
      const sliderPercent = ((safeValue - normalizedMin) / safeRange) * 100;
      seatsSlider.style.setProperty("--slider-progress", `${sliderPercent}%`);

      if (!Number.isFinite(sliderValue) || sliderValue <= 0) {
        seatsSliderValue.textContent = "Any";
        return;
      }

      seatsSliderValue.textContent = `${sliderValue}+`;
    };

    seatsSlider.addEventListener("input", syncSeatValue);
    syncSeatValue();
  };

  const initBookingFilterDrawer = () => {
    const bookingSelectLayout = document.querySelector(
      ".booking-select-layout",
    );
    const filterPanel = document.querySelector(".booking-filter");
    const resultsTop = document.querySelector(".booking-select-results__top");

    if (
      !(bookingSelectLayout instanceof HTMLElement) ||
      !(filterPanel instanceof HTMLElement) ||
      !(resultsTop instanceof HTMLElement)
    ) {
      return;
    }

    const mobileQuery = window.matchMedia("(max-width: 820px)");
    let overlay = document.querySelector(".booking-filter-overlay");
    if (!(overlay instanceof HTMLElement)) {
      overlay = document.createElement("button");
      overlay.type = "button";
      overlay.className = "booking-filter-overlay";
      overlay.setAttribute("aria-label", "Close filters");
      document.body.appendChild(overlay);
    }

    let toggleButton = resultsTop.querySelector(
      ".booking-select-results__filter-toggle",
    );
    if (!(toggleButton instanceof HTMLButtonElement)) {
      toggleButton = document.createElement("button");
      toggleButton.type = "button";
      toggleButton.className = "booking-select-results__filter-toggle";
      toggleButton.setAttribute("aria-label", "Open filters");
      toggleButton.innerHTML =
        '<span class="material-symbols-rounded" aria-hidden="true">tune</span>';
      resultsTop.appendChild(toggleButton);
    }

    const closeDrawer = () => {
      filterPanel.classList.remove("filter-visible");
      overlay.classList.remove("is-visible");
      document.body.classList.remove("booking-filter-open");
      toggleButton.setAttribute("aria-expanded", "false");
    };

    const openDrawer = () => {
      filterPanel.classList.add("offcanvas", "filter-visible");
      overlay.classList.add("is-visible");
      document.body.classList.add("booking-filter-open");
      toggleButton.setAttribute("aria-expanded", "true");
    };

    const syncMode = () => {
      if (!mobileQuery.matches) {
        closeDrawer();
        filterPanel.classList.remove("offcanvas");
        return;
      }

      filterPanel.classList.add("offcanvas");
      // Ensure drawer starts closed on small screens
      closeDrawer();
    };

    toggleButton.addEventListener("click", () => {
      if (!mobileQuery.matches) {
        return;
      }

      if (filterPanel.classList.contains("filter-visible")) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });

    overlay.addEventListener("click", closeDrawer);

    // Close the filter drawer when the Apply button is clicked
    const applyButton = filterPanel.querySelector(".booking-filter__apply");
    if (applyButton instanceof HTMLButtonElement) {
      applyButton.addEventListener("click", (evt) => {
        // Close the drawer immediately when Apply is clicked
        // This ensures the drawer is hidden before the page reloads
        closeDrawer();
      });
    }

    // Also ensure cleanup happens if the form is submitted
    const filterForm = filterPanel.querySelector(".booking-filter__form");
    if (filterForm instanceof HTMLFormElement) {
      filterForm.addEventListener("submit", () => {
        // Immediately close the drawer on form submission
        // Use a small delay to ensure the form submission completes
        closeDrawer();
      });
    }

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeDrawer();
      }
    });

    window.addEventListener("resize", syncMode);
    // On page load, force cleanup of any residual state first
    filterPanel.classList.remove("filter-visible");
    overlay.classList.remove("is-visible");
    document.body.classList.remove("booking-filter-open");
    // Then initialize the mode (drawer or sidebar based on viewport)
    syncMode();
  };

  const initUserBookingCancellationModal = () => {
    const cancelTriggers = Array.from(
      document.querySelectorAll("[data-user-booking-cancel-trigger='true']"),
    );

    if (!cancelTriggers.length) {
      return;
    }

    const cancelBookingIdInput = document.querySelector(
      "[data-user-booking-cancel-id-input]",
    );
    const cancelHistoryTabInput = document.querySelector(
      "[data-user-booking-cancel-tab-input]",
    );

    if (!(cancelBookingIdInput instanceof HTMLInputElement)) {
      return;
    }

    cancelTriggers.forEach((cancelTrigger) => {
      cancelTrigger.addEventListener("click", () => {
        const bookingId = String(
          cancelTrigger.getAttribute("data-user-booking-cancel-id") || "0",
        ).trim();
        const bookingTab = String(
          cancelTrigger.getAttribute("data-user-booking-cancel-tab") ||
            "pending",
        )
          .trim()
          .toLowerCase();

        cancelBookingIdInput.value = bookingId !== "" ? bookingId : "0";

        if (cancelHistoryTabInput instanceof HTMLInputElement) {
          cancelHistoryTabInput.value = [
            "active",
            "pending",
            "completed",
            "cancelled",
          ].includes(bookingTab)
            ? bookingTab
            : "pending";
        }
      });
    });
  };

  const initNativeFallback = () => {
    DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      input.type = "date";
      input.placeholder = "";
      input.min = getTodayIsoDate();
    });

    MAINTENANCE_DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      input.type = "date";
      input.placeholder = "";
      input.min = getTodayIsoDate();
    });

    CREATE_VEHICLE_DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      input.type = "date";
      input.placeholder = "";
      input.max = getTodayIsoDate();
    });

    USER_REGISTER_DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      input.type = "text";
      input.placeholder = "dd/mm/yyyy";
      input.inputMode = "numeric";
    });

    TIME_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      input.type = "time";
      input.placeholder = "";
      input.step = "300";
    });
  };

  const initFlatpickrPickers = () => {
    if (typeof window.flatpickr !== "function") {
      initNativeFallback();
      return;
    }

    const sharedPickerOptions = {
      allowInput: true,
      disableMobile: true,
      static: true,
      monthSelectorType: "static",
    };

    const dateOptions = {
      ...sharedPickerOptions,
      dateFormat: "d/m/Y",
      prevArrow:
        '<span class="material-symbols-rounded" aria-hidden="true">chevron_left</span>',
      nextArrow:
        '<span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>',
    };

    const bookingDateOptions = {
      ...dateOptions,
      minDate: "today",
    };

    const serviceDateOptions = {
      ...dateOptions,
      maxDate: "today",
    };

    const maintenanceDateOptions = {
      ...sharedPickerOptions,
      dateFormat: "d M, Y",
      minDate: "today",
      prevArrow:
        '<span class="material-symbols-rounded" aria-hidden="true">chevron_left</span>',
      nextArrow:
        '<span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>',
    };

    const userRegisterDateOptions = {
      ...dateOptions,
      maxDate: "today",
      static: true,
      onReady: function (_selectedDates, _dateStr, instance) {
        if (instance && instance._input) {
          instance._input.removeAttribute("readonly");
          if (!instance._input.placeholder) {
            instance._input.placeholder = "dd/mm/yyyy";
          }
        }
      },
      onOpen: function (_selectedDates, _dateStr, instance) {
        if (instance && instance.calendarContainer) {
          instance.calendarContainer.classList.add(
            "user-register-date-flatpickr",
          );
        }
      },
    };

    DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      window.flatpickr(input, bookingDateOptions);
    });

    MAINTENANCE_DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      window.flatpickr(input, maintenanceDateOptions);
    });

    CREATE_VEHICLE_DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      window.flatpickr(input, serviceDateOptions);
    });

    USER_REGISTER_DATE_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      window.flatpickr(input, userRegisterDateOptions);
    });

    const timeOptions = {
      ...sharedPickerOptions,
      enableTime: true,
      noCalendar: true,
      dateFormat: "h:i K",
      minuteIncrement: 5,
    };

    TIME_INPUT_IDS.forEach((id) => {
      const input = getInputById(id);
      if (!input) {
        return;
      }

      window.flatpickr(input, timeOptions);
    });
  };

  const initBookingPickers = () => {
    const hasBookingEngine = Boolean(document.querySelector(".booking-engine"));
    const hasMaintenancePickers = Boolean(
      document.querySelector("[data-maintenance-estimate-input]") ||
      document.querySelector("[data-maintenance-edit-estimate-input]"),
    );
    const hasCreateVehicleDatePicker = Boolean(
      document.querySelector("[data-create-last-service-input]") ||
      document.querySelector("[data-edit-last-service-input]"),
    );
    const hasUserRegisterDatePicker = Boolean(
      document.getElementById("user-register-date-of-birth"),
    );
    const hasBookNowTrigger = Boolean(
      document.querySelector(
        "[data-book-now-trigger][data-modal-target='user-booking-confirm-modal']",
      ),
    );
    const hasSeatsSlider = Boolean(
      document.querySelector("[data-seats-slider]"),
    );
    const hasUserBookingCancellationModal = Boolean(
      document.querySelector("[data-user-booking-cancel-trigger='true']"),
    );

    if (
      !hasBookingEngine &&
      !hasMaintenancePickers &&
      !hasCreateVehicleDatePicker &&
      !hasUserRegisterDatePicker &&
      !hasBookNowTrigger &&
      !hasSeatsSlider &&
      !hasUserBookingCancellationModal
    ) {
      return;
    }

    initVehicleTypeTabs();
    initFlatpickrPickers();
    bindPickerTriggers();
    initHeroBookNowAction();
    initSameReturnLocationSync();
    initBookingFormValidation();
    initBookNowConfirmationContext();
    initBookingSeatSlider();
    initUserBookingCancellationModal();
    initBookingFilterDrawer();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initBookingPickers);
    return;
  }

  initBookingPickers();
})();
