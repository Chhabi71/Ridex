/**
 * Purpose: Client-side step validation and UI behavior for user create-account modals.
 * Website Section: User Authentication (Create Account).
 */

(function () {
  "use strict";

  var registerForm = document.getElementById("user-register-form");
  var autoOpenTriggers = Array.from(
    document.querySelectorAll(
      "[data-user-register-auto-open='true'], [data-user-auth-auto-open='true']",
    ),
  );

  autoOpenTriggers.forEach(function (triggerNode) {
    if (!(triggerNode instanceof HTMLElement)) {
      return;
    }

    window.setTimeout(function () {
      triggerNode.click();
    }, 0);
  });

  var forgotEmailInput = document.getElementById("user-forgot-email-input");
  var forgotDriverIdInput = document.getElementById(
    "user-forgot-driver-id-input",
  );
  var forgotEmailContinueButton = document.querySelector(
    "[data-user-forgot-email-continue]",
  );
  var forgotDriverContinueButton = document.querySelector(
    "[data-user-forgot-driver-continue]",
  );
  var forgotEmailPreviewNodes = Array.from(
    document.querySelectorAll("[data-user-forgot-email-preview]"),
  );
  var allowForgotEmailTransition = false;
  var allowForgotDriverTransition = false;
  var verifiedForgotEmail = "";

  var verifyForgotStep = function (stepName, payload) {
    var body = new URLSearchParams();
    body.set("step", String(stepName || "").trim());

    Object.keys(payload || {}).forEach(function (key) {
      body.set(key, String(payload[key] || "").trim());
    });

    return fetch("index.php?page=user-auth-lookup", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: body.toString(),
      credentials: "same-origin",
    })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return {
              ok: false,
              message: "Unexpected server response.",
            };
          })
          .then(function (payloadResponse) {
            var responseOk =
              response.ok && payloadResponse && payloadResponse.ok === true;

            return {
              ok: responseOk,
              message:
                String((payloadResponse && payloadResponse.message) || "") ||
                "Unable to verify.",
            };
          });
      })
      .catch(function () {
        return {
          ok: false,
          message: "Unable to verify right now. Please try again.",
        };
      });
  };

  var syncForgotEmailPreview = function () {
    if (!(forgotEmailInput instanceof HTMLInputElement)) {
      return;
    }

    var emailText = forgotEmailInput.value.trim();
    if (emailText === "") {
      emailText = "example@ridex.com";
    }

    forgotEmailPreviewNodes.forEach(function (previewNode) {
      if (previewNode instanceof HTMLElement) {
        previewNode.textContent = emailText;
      }
    });
  };

  var validateForgotField = function (inputNode, emptyMessage) {
    if (!(inputNode instanceof HTMLInputElement)) {
      return true;
    }

    if (inputNode.value.trim() === "") {
      inputNode.setCustomValidity(emptyMessage);
      inputNode.reportValidity();
      return false;
    }

    inputNode.setCustomValidity("");

    if (inputNode.type === "email" && !inputNode.checkValidity()) {
      inputNode.reportValidity();
      return false;
    }

    return true;
  };

  if (forgotEmailContinueButton instanceof HTMLElement) {
    forgotEmailContinueButton.addEventListener(
      "click",
      function (event) {
        if (allowForgotEmailTransition) {
          allowForgotEmailTransition = false;
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        var isForgotEmailValid = validateForgotField(
          forgotEmailInput,
          "Please enter your registered email.",
        );

        if (!isForgotEmailValid) {
          return;
        }

        if (!(forgotEmailInput instanceof HTMLInputElement)) {
          return;
        }

        var forgotEmailValue = forgotEmailInput.value.trim();
        forgotEmailContinueButton.disabled = true;

        verifyForgotStep("email", {
          email: forgotEmailValue,
        })
          .then(function (verificationResult) {
            if (!verificationResult.ok) {
              forgotEmailInput.setCustomValidity(
                verificationResult.message ||
                  "No user account found for this email.",
              );
              forgotEmailInput.reportValidity();
              return;
            }

            forgotEmailInput.setCustomValidity("");
            verifiedForgotEmail = forgotEmailValue;
            syncForgotEmailPreview();

            forgotEmailContinueButton.disabled = false;
            allowForgotEmailTransition = true;
            forgotEmailContinueButton.click();
          })
          .finally(function () {
            forgotEmailContinueButton.disabled = false;
          });
      },
      true,
    );
  }

  if (forgotDriverContinueButton instanceof HTMLElement) {
    forgotDriverContinueButton.addEventListener(
      "click",
      function (event) {
        if (allowForgotDriverTransition) {
          allowForgotDriverTransition = false;
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        var isDriverIdValid = validateForgotField(
          forgotDriverIdInput,
          "Please enter your Driver ID.",
        );

        if (!isDriverIdValid) {
          return;
        }

        if (!(forgotDriverIdInput instanceof HTMLInputElement)) {
          return;
        }

        var emailForLookup = verifiedForgotEmail;
        if (
          emailForLookup === "" &&
          forgotEmailInput instanceof HTMLInputElement
        ) {
          emailForLookup = forgotEmailInput.value.trim();
        }

        if (emailForLookup === "") {
          forgotDriverIdInput.setCustomValidity(
            "Please verify your email first.",
          );
          forgotDriverIdInput.reportValidity();
          return;
        }

        var forgotDriverValue = forgotDriverIdInput.value.trim();
        forgotDriverContinueButton.disabled = true;

        verifyForgotStep("driver", {
          email: emailForLookup,
          drivers_id: forgotDriverValue,
        })
          .then(function (verificationResult) {
            if (!verificationResult.ok) {
              forgotDriverIdInput.setCustomValidity(
                verificationResult.message ||
                  "Driver ID does not match this email.",
              );
              forgotDriverIdInput.reportValidity();
              return;
            }

            forgotDriverIdInput.setCustomValidity("");

            forgotDriverContinueButton.disabled = false;
            allowForgotDriverTransition = true;
            forgotDriverContinueButton.click();
          })
          .finally(function () {
            forgotDriverContinueButton.disabled = false;
          });
      },
      true,
    );
  }

  if (forgotEmailInput instanceof HTMLInputElement) {
    forgotEmailInput.addEventListener("input", function () {
      forgotEmailInput.setCustomValidity("");
      verifiedForgotEmail = "";
      syncForgotEmailPreview();
    });
  }

  if (forgotDriverIdInput instanceof HTMLInputElement) {
    forgotDriverIdInput.addEventListener("input", function () {
      forgotDriverIdInput.setCustomValidity("");
    });
  }

  if (!(registerForm instanceof HTMLFormElement)) {
    return;
  }

  var newsletterCheckbox = document.querySelector(
    "[data-user-register-newsletter]",
  );
  var newsletterHiddenInput = registerForm.querySelector(
    "[data-user-register-newsletter-hidden]",
  );
  var dateOfBirthInput = document.getElementById("user-register-date-of-birth");
  var driversIdInput = document.getElementById("user-register-drivers-id");
  var driversIdImageInput = document.getElementById(
    "user-register-drivers-id-image",
  );
  var driversIdImageTriggerButton = document.querySelector(
    "[data-user-register-driver-image-trigger]",
  );
  var driversIdImageClearButton = document.querySelector(
    "[data-user-register-driver-image-clear]",
  );
  var driversIdImagePreviewImage = document.querySelector(
    "[data-user-register-driver-image-preview-image]",
  );
  var driversIdImagePreviewPlaceholder = document.querySelector(
    "[data-user-register-driver-image-preview-placeholder]",
  );
  var driversIdImagePreviewName = document.querySelector(
    "[data-user-register-driver-image-preview-name]",
  );
  var driversIdImageMessage = document.querySelector(
    "[data-user-register-driver-image-message]",
  );
  var driversIdImageObjectUrl = "";
  var phoneInput = document.getElementById("user-register-phone");
  var passwordInput = document.querySelector("[data-user-register-password]");
  var passwordRuleNodes = {
    lowercase: document.querySelector("[data-password-rule='lowercase']"),
    uppercase: document.querySelector("[data-password-rule='uppercase']"),
    digit: document.querySelector("[data-password-rule='digit']"),
    symbol: document.querySelector("[data-password-rule='symbol']"),
    length: document.querySelector("[data-password-rule='length']"),
  };
  var termsCheckboxes = [
    document.getElementById("user-register-terms-privacy"),
    document.getElementById("user-register-terms-deposit"),
    document.getElementById("user-register-terms-damage"),
  ].filter(function (checkbox) {
    return checkbox instanceof HTMLInputElement;
  });
  var submitTermsButton = document.querySelector("[data-user-register-submit]");
  var allowPersonalStepTransition = false;
  var allowRegisterSubmit = false;
  var verifiedRegisterDriverId = "";
  var isVerifiedRegisterDriverIdAvailable = false;

  var stepFieldIds = {
    personal: [
      "user-register-first-name",
      "user-register-last-name",
      "user-register-date-of-birth",
      "user-register-drivers-id",
      "user-register-drivers-id-image",
    ],
    contact: ["user-register-phone", "user-register-email"],
    address: [
      "user-register-street",
      "user-register-province",
      "user-register-post-code",
      "user-register-city",
    ],
    password: ["user-register-password-input"],
    terms: [
      "user-register-terms-privacy",
      "user-register-terms-deposit",
      "user-register-terms-damage",
    ],
  };

  var PHONE_PREFIX = "+977 ";

  var resetRegisterDriverIdVerification = function () {
    allowPersonalStepTransition = false;
    verifiedRegisterDriverId = "";
    isVerifiedRegisterDriverIdAvailable = false;
  };

  var resetDriversIdImagePreview = function () {
    if (driversIdImageObjectUrl !== "") {
      URL.revokeObjectURL(driversIdImageObjectUrl);
      driversIdImageObjectUrl = "";
    }

    if (driversIdImagePreviewImage instanceof HTMLImageElement) {
      driversIdImagePreviewImage.hidden = true;
      driversIdImagePreviewImage.removeAttribute("src");
    }

    if (driversIdImagePreviewPlaceholder instanceof HTMLElement) {
      driversIdImagePreviewPlaceholder.hidden = false;
    }

    if (driversIdImagePreviewName instanceof HTMLElement) {
      driversIdImagePreviewName.hidden = true;
      driversIdImagePreviewName.textContent = "";
    }

    if (driversIdImageClearButton instanceof HTMLButtonElement) {
      driversIdImageClearButton.hidden = true;
    }

    if (driversIdImageMessage instanceof HTMLElement) {
      driversIdImageMessage.hidden = true;
      driversIdImageMessage.textContent = "";
    }
  };

  var syncDriversIdImagePreview = function () {
    if (!(driversIdImageInput instanceof HTMLInputElement)) {
      return;
    }

    driversIdImageInput.setCustomValidity("");

    var selectedFile =
      driversIdImageInput.files && driversIdImageInput.files.length > 0
        ? driversIdImageInput.files[0]
        : null;

    if (!selectedFile) {
      resetDriversIdImagePreview();
      return;
    }

    if (driversIdImagePreviewPlaceholder instanceof HTMLElement) {
      driversIdImagePreviewPlaceholder.hidden = true;
    }

    if (driversIdImagePreviewName instanceof HTMLElement) {
      driversIdImagePreviewName.hidden = false;
      driversIdImagePreviewName.textContent = selectedFile.name;
    }

    if (driversIdImagePreviewImage instanceof HTMLImageElement) {
      if (driversIdImageObjectUrl !== "") {
        URL.revokeObjectURL(driversIdImageObjectUrl);
      }

      driversIdImageObjectUrl = URL.createObjectURL(selectedFile);
      driversIdImagePreviewImage.src = driversIdImageObjectUrl;
      driversIdImagePreviewImage.hidden = false;
    }

    if (driversIdImageClearButton instanceof HTMLButtonElement) {
      driversIdImageClearButton.hidden = false;
    }
  };

  var validateDriversIdImage = function () {
    if (!(driversIdImageInput instanceof HTMLInputElement)) {
      return true;
    }

    var selectedFile =
      driversIdImageInput.files && driversIdImageInput.files.length > 0
        ? driversIdImageInput.files[0]
        : null;

    if (selectedFile === null) {
      driversIdImageInput.setCustomValidity("Driver's ID image is required.");
      if (driversIdImageMessage instanceof HTMLElement) {
        driversIdImageMessage.hidden = false;
        driversIdImageMessage.textContent = "Driver's ID image is required.";
      }
      driversIdImageInput.reportValidity();
      return false;
    }

    driversIdImageInput.setCustomValidity("");
    if (driversIdImageMessage instanceof HTMLElement) {
      driversIdImageMessage.hidden = true;
      driversIdImageMessage.textContent = "";
    }
    return true;
  };

  var parseRegisterDate = function (rawDate) {
    var normalized = String(rawDate || "").trim();
    if (normalized === "") {
      return null;
    }

    var match = /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/.exec(normalized);
    if (!match) {
      var isoMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(normalized);
      if (!isoMatch) {
        return null;
      }

      match = [isoMatch[0], isoMatch[3], isoMatch[2], isoMatch[1]];
    }

    var day = Number.parseInt(match[1], 10);
    var month = Number.parseInt(match[2], 10);
    var year = Number.parseInt(match[3], 10);
    if (
      !Number.isFinite(day) ||
      !Number.isFinite(month) ||
      !Number.isFinite(year)
    ) {
      return null;
    }

    var parsedDate = new Date(year, month - 1, day, 0, 0, 0, 0);
    if (
      Number.isNaN(parsedDate.getTime()) ||
      parsedDate.getFullYear() !== year ||
      parsedDate.getMonth() !== month - 1 ||
      parsedDate.getDate() !== day
    ) {
      return null;
    }

    return parsedDate;
  };

  var calculateAgeInYears = function (birthDate, todayDate) {
    var age = todayDate.getFullYear() - birthDate.getFullYear();
    var monthDiff = todayDate.getMonth() - birthDate.getMonth();
    if (
      monthDiff < 0 ||
      (monthDiff === 0 && todayDate.getDate() < birthDate.getDate())
    ) {
      age -= 1;
    }

    return age;
  };

  var validateRegisterDateOfBirth = function () {
    if (!(dateOfBirthInput instanceof HTMLInputElement)) {
      return true;
    }

    var rawDate = dateOfBirthInput.value.trim();
    if (rawDate === "") {
      dateOfBirthInput.setCustomValidity("Date of birth is required.");
      return false;
    }

    var parsedBirthDate = parseRegisterDate(rawDate);
    if (!(parsedBirthDate instanceof Date)) {
      dateOfBirthInput.setCustomValidity("Please enter a valid date of birth.");
      return false;
    }

    var today = new Date();
    today.setHours(0, 0, 0, 0);

    if (parsedBirthDate > today) {
      dateOfBirthInput.setCustomValidity(
        "Date of birth cannot be in the future.",
      );
      return false;
    }

    var age = calculateAgeInYears(parsedBirthDate, today);
    if (!Number.isFinite(age) || age < 18) {
      dateOfBirthInput.setCustomValidity(
        "You must be at least 18 years old to register.",
      );
      return false;
    }

    dateOfBirthInput.setCustomValidity("");
    return true;
  };

  var verifyRegisterDriverIdAvailability = function (driversId) {
    return verifyForgotStep("register-driver", {
      drivers_id: String(driversId || "").trim(),
    });
  };

  var getPhoneLocalDigits = function (rawPhone) {
    var digits = String(rawPhone || "").replace(/\D+/g, "");

    if (digits.indexOf("977") === 0) {
      digits = digits.slice(3);
    }

    return digits.slice(0, 10);
  };

  var keepPhoneCaretAtEnd = function () {
    if (
      !(phoneInput instanceof HTMLInputElement) ||
      typeof phoneInput.setSelectionRange !== "function"
    ) {
      return;
    }

    var caret = phoneInput.value.length;
    phoneInput.setSelectionRange(caret, caret);
  };

  var applyPhoneFormattingAndValidation = function () {
    if (!(phoneInput instanceof HTMLInputElement)) {
      return true;
    }

    var localDigits = getPhoneLocalDigits(phoneInput.value);
    phoneInput.value = PHONE_PREFIX + localDigits;
    var isValidPhone = /^9\d{9}$/.test(localDigits);

    if (!isValidPhone) {
      phoneInput.setCustomValidity(
        "Phone number must be in +977 9XXXXXXXXX format.",
      );
      return false;
    }

    phoneInput.setCustomValidity("");
    return true;
  };

  var syncNewsletterValue = function () {
    if (
      !(newsletterCheckbox instanceof HTMLInputElement) ||
      !(newsletterHiddenInput instanceof HTMLInputElement)
    ) {
      return;
    }

    newsletterHiddenInput.value = newsletterCheckbox.checked ? "1" : "0";
  };

  var evaluatePasswordRules = function (rawPassword) {
    var passwordValue = String(rawPassword || "");

    return {
      lowercase: /[a-z]/.test(passwordValue),
      uppercase: /[A-Z]/.test(passwordValue),
      digit: /\d/.test(passwordValue),
      symbol: /[^a-zA-Z\d]/.test(passwordValue),
      length: passwordValue.length >= 8,
    };
  };

  var applyPasswordRuleUi = function (rules) {
    Object.keys(passwordRuleNodes).forEach(function (ruleKey) {
      var ruleNode = passwordRuleNodes[ruleKey];
      if (!(ruleNode instanceof HTMLElement)) {
        return;
      }

      ruleNode.classList.toggle("is-valid", rules[ruleKey] === true);
    });
  };

  var updatePasswordValidity = function () {
    if (!(passwordInput instanceof HTMLInputElement)) {
      return true;
    }

    var rules = evaluatePasswordRules(passwordInput.value);
    applyPasswordRuleUi(rules);

    var isValid =
      rules.lowercase &&
      rules.uppercase &&
      rules.digit &&
      rules.symbol &&
      rules.length;

    if (!isValid) {
      passwordInput.setCustomValidity(
        "Password must contain lowercase, uppercase, digit, symbol, and at least 8 characters.",
      );
      return false;
    }

    passwordInput.setCustomValidity("");
    return true;
  };

  var getStepFields = function (stepName) {
    var fieldIds = stepFieldIds[stepName] || [];
    var fields = [];

    for (var index = 0; index < fieldIds.length; index += 1) {
      var fieldNode = document.getElementById(fieldIds[index]);
      if (
        fieldNode instanceof HTMLInputElement ||
        fieldNode instanceof HTMLSelectElement
      ) {
        fields.push(fieldNode);
      }
    }

    return fields;
  };

  var validateFields = function (fields) {
    for (var index = 0; index < fields.length; index += 1) {
      var field = fields[index];
      if (typeof field.checkValidity === "function" && !field.checkValidity()) {
        if (typeof field.reportValidity === "function") {
          field.reportValidity();
        }
        return false;
      }
    }

    return true;
  };

  var validateStep = function (stepName) {
    var fields = getStepFields(stepName);
    if (!validateFields(fields)) {
      return false;
    }

    if (stepName === "personal") {
      if (!validateRegisterDateOfBirth()) {
        if (dateOfBirthInput instanceof HTMLInputElement) {
          dateOfBirthInput.reportValidity();
        }
        return false;
      }

      if (driversIdInput instanceof HTMLInputElement) {
        var localDriversId = driversIdInput.value.trim();
        if (localDriversId === "") {
          driversIdInput.setCustomValidity("Driver's ID is required.");
          driversIdInput.reportValidity();
          return false;
        }

        driversIdInput.setCustomValidity("");
      }

      if (!validateDriversIdImage()) {
        return false;
      }
    }

    if (stepName === "contact") {
      var isPhoneValid = applyPhoneFormattingAndValidation();
      if (!isPhoneValid) {
        if (phoneInput instanceof HTMLInputElement) {
          phoneInput.reportValidity();
        }
        return false;
      }
    }

    if (stepName === "terms") {
      var allTermsAccepted = termsCheckboxes.every(function (checkbox) {
        return checkbox.checked === true;
      });

      if (!allTermsAccepted) {
        var firstUnchecked = null;

        termsCheckboxes.forEach(function (checkbox) {
          var isChecked = checkbox.checked === true;
          checkbox.setCustomValidity(
            isChecked ? "" : "Please accept all three policies to continue.",
          );

          if (!isChecked && firstUnchecked === null) {
            firstUnchecked = checkbox;
          }
        });

        if (
          firstUnchecked instanceof HTMLInputElement &&
          typeof firstUnchecked.reportValidity === "function"
        ) {
          firstUnchecked.reportValidity();
        }

        return false;
      }

      termsCheckboxes.forEach(function (checkbox) {
        checkbox.setCustomValidity("");
      });
    }

    if (stepName === "password") {
      var isPasswordValid = updatePasswordValidity();
      if (!isPasswordValid) {
        if (passwordInput instanceof HTMLInputElement) {
          passwordInput.reportValidity();
        }
        return false;
      }
    }

    return true;
  };

  var nextButtons = Array.from(
    document.querySelectorAll(
      "[data-user-register-next][data-user-register-step]",
    ),
  );

  nextButtons.forEach(function (nextButton) {
    nextButton.addEventListener(
      "click",
      function (event) {
        syncNewsletterValue();

        var stepName = String(
          nextButton.getAttribute("data-user-register-step") || "",
        ).trim();

        if (stepName === "") {
          return;
        }

        if (stepName === "personal") {
          if (allowPersonalStepTransition) {
            allowPersonalStepTransition = false;
            return;
          }

          if (!validateStep(stepName)) {
            event.preventDefault();
            event.stopPropagation();
            return;
          }

          if (!(driversIdInput instanceof HTMLInputElement)) {
            return;
          }

          var driversIdForCheck = driversIdInput.value.trim();
          var isAlreadyVerifiedDriverId =
            isVerifiedRegisterDriverIdAvailable &&
            verifiedRegisterDriverId.toLowerCase() ===
              driversIdForCheck.toLowerCase();

          if (isAlreadyVerifiedDriverId) {
            return;
          }

          event.preventDefault();
          event.stopPropagation();
          nextButton.disabled = true;

          verifyRegisterDriverIdAvailability(driversIdForCheck)
            .then(function (verificationResult) {
              if (!verificationResult.ok) {
                resetRegisterDriverIdVerification();
                driversIdInput.setCustomValidity(
                  verificationResult.message ||
                    "This Driver ID has already been used to create an account.",
                );
                driversIdInput.reportValidity();
                return;
              }

              driversIdInput.setCustomValidity("");
              verifiedRegisterDriverId = driversIdForCheck;
              isVerifiedRegisterDriverIdAvailable = true;
              allowPersonalStepTransition = true;
              nextButton.click();
            })
            .finally(function () {
              nextButton.disabled = false;
            });

          return;
        }

        if (validateStep(stepName)) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
      },
      true,
    );
  });

  registerForm.addEventListener(
    "submit",
    function (event) {
      if (allowRegisterSubmit) {
        allowRegisterSubmit = false;
        return;
      }

      syncNewsletterValue();

      var allValid =
        validateStep("personal") &&
        validateStep("contact") &&
        validateStep("address") &&
        validateStep("password") &&
        validateStep("terms");

      if (!allValid) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      if (!(driversIdInput instanceof HTMLInputElement)) {
        return;
      }

      var driversIdForSubmit = driversIdInput.value.trim();
      var isDriverIdVerifiedForSubmit =
        isVerifiedRegisterDriverIdAvailable &&
        verifiedRegisterDriverId.toLowerCase() ===
          driversIdForSubmit.toLowerCase();
      if (isDriverIdVerifiedForSubmit) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      verifyRegisterDriverIdAvailability(driversIdForSubmit).then(
        function (verificationResult) {
          if (!verificationResult.ok) {
            resetRegisterDriverIdVerification();
            driversIdInput.setCustomValidity(
              verificationResult.message ||
                "This Driver ID has already been used to create an account.",
            );
            driversIdInput.reportValidity();
            return;
          }

          driversIdInput.setCustomValidity("");
          verifiedRegisterDriverId = driversIdForSubmit;
          isVerifiedRegisterDriverIdAvailable = true;
          allowRegisterSubmit = true;

          if (typeof registerForm.requestSubmit === "function") {
            registerForm.requestSubmit();
            return;
          }

          registerForm.submit();
        },
      );
    },
    true,
  );

  if (dateOfBirthInput instanceof HTMLInputElement) {
    dateOfBirthInput.addEventListener("input", function () {
      dateOfBirthInput.setCustomValidity("");
    });

    dateOfBirthInput.addEventListener("change", function () {
      validateRegisterDateOfBirth();
    });
  }

  if (driversIdInput instanceof HTMLInputElement) {
    driversIdInput.addEventListener("input", function () {
      driversIdInput.setCustomValidity("");
      resetRegisterDriverIdVerification();
    });

    driversIdInput.addEventListener("change", function () {
      driversIdInput.setCustomValidity("");
      resetRegisterDriverIdVerification();
    });
  }

  if (driversIdImageInput instanceof HTMLInputElement) {
    driversIdImageInput.addEventListener("change", function () {
      syncDriversIdImagePreview();
    });

    driversIdImageInput.addEventListener("input", function () {
      syncDriversIdImagePreview();
    });
  }

  if (driversIdImageTriggerButton instanceof HTMLElement) {
    driversIdImageTriggerButton.addEventListener("click", function () {
      if (driversIdImageInput instanceof HTMLInputElement) {
        driversIdImageInput.click();
      }
    });
  }

  if (driversIdImageClearButton instanceof HTMLElement) {
    driversIdImageClearButton.addEventListener("click", function () {
      if (driversIdImageInput instanceof HTMLInputElement) {
        driversIdImageInput.value = "";
        driversIdImageInput.setCustomValidity("");
      }

      syncDriversIdImagePreview();
    });
  }

  syncDriversIdImagePreview();

  if (newsletterCheckbox instanceof HTMLInputElement) {
    newsletterCheckbox.addEventListener("change", syncNewsletterValue);
    syncNewsletterValue();
  }

  if (phoneInput instanceof HTMLInputElement) {
    phoneInput.addEventListener("focus", function () {
      if (phoneInput.value.trim() === "") {
        phoneInput.value = PHONE_PREFIX;
      }

      keepPhoneCaretAtEnd();
    });

    phoneInput.addEventListener("input", function () {
      applyPhoneFormattingAndValidation();
      keepPhoneCaretAtEnd();
    });

    phoneInput.addEventListener("blur", function () {
      applyPhoneFormattingAndValidation();
    });

    if (phoneInput.value.trim() === "") {
      phoneInput.value = PHONE_PREFIX;
    }

    applyPhoneFormattingAndValidation();
  }

  if (passwordInput instanceof HTMLInputElement) {
    passwordInput.addEventListener("input", updatePasswordValidity);
    passwordInput.addEventListener("change", updatePasswordValidity);
    updatePasswordValidity();
  }

  if (
    submitTermsButton instanceof HTMLButtonElement &&
    termsCheckboxes.length
  ) {
    var syncTermsSubmitState = function () {
      var allTermsAccepted = termsCheckboxes.every(function (checkbox) {
        return checkbox.checked === true;
      });

      submitTermsButton.disabled = !allTermsAccepted;
    };

    termsCheckboxes.forEach(function (checkbox) {
      checkbox.addEventListener("change", syncTermsSubmitState);
    });

    syncTermsSubmitState();
  }
})();
