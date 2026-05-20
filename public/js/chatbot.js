(() => {
  const root = document.querySelector("[data-ridex-chatbot]");
  if (!(root instanceof HTMLElement)) {
    return;
  }

  const toggleButton = root.querySelector("[data-chatbot-toggle]");
  const closeButton = root.querySelector("[data-chatbot-close]");
  const panel = root.querySelector("[data-chatbot-panel]");
  const form = root.querySelector("[data-chatbot-form]");
  const input = root.querySelector("[data-chatbot-input]");
  const messages = root.querySelector("[data-chatbot-messages]");

  if (
    !(toggleButton instanceof HTMLButtonElement) ||
    !(closeButton instanceof HTMLButtonElement) ||
    !(panel instanceof HTMLElement) ||
    !(form instanceof HTMLFormElement) ||
    !(input instanceof HTMLInputElement) ||
    !(messages instanceof HTMLElement)
  ) {
    return;
  }

  /*
    Chat history storage

    Goal:
    1. Keep chatbot history while the user moves between pages.
    2. Clear chatbot history when the user logs out.
    3. Start fresh when the user logs in again.

    sessionStorage is used instead of localStorage because:
    - it keeps history during the current browsing session
    - it clears more safely when browser/session changes
    - it avoids old history returning again after logout/re-login
  */

  const getCurrentChatAuthState = () => {
    const isLoggedIn = document.body.dataset.userAuthenticated === "1";

    const userId =
      window.RIDEX_USER_ID ||
      document.body.dataset.userId ||
      (isLoggedIn ? "logged_user" : "guest");

    return isLoggedIn ? `user_${userId}` : "guest";
  };

  const getChatStorageKey = () => {
    return `ridex_chat_history_${getCurrentChatAuthState()}`;
  };

  const clearAllRidexChatHistory = () => {
    try {
      Object.keys(sessionStorage).forEach((key) => {
        if (key.startsWith("ridex_chat_history_")) {
          sessionStorage.removeItem(key);
        }
      });
    } catch (error) {
      // If browser storage fails, chatbot should still work normally.
    }
  };

  const initializeChatSession = () => {
    const currentAuthState = getCurrentChatAuthState();
    const previousAuthState = sessionStorage.getItem("ridex_chat_auth_state");

    /*
      If auth state changed, clear old chatbot history.

      Examples:
      user_logged_user -> guest = user logged out
      guest -> user_logged_user = user logged in again

      This prevents old chat history from coming back after logout/login.
    */
    if (previousAuthState && previousAuthState !== currentAuthState) {
      clearAllRidexChatHistory();
    }

    sessionStorage.setItem("ridex_chat_auth_state", currentAuthState);
  };

  const readChatHistory = () => {
    try {
      return JSON.parse(sessionStorage.getItem(getChatStorageKey()) || "[]");
    } catch (error) {
      return [];
    }
  };

  const writeChatHistory = (history) => {
    try {
      // Keep only last 40 chat items so browser storage does not become too large.
      sessionStorage.setItem(getChatStorageKey(), JSON.stringify(history.slice(-40)));
    } catch (error) {
      // If browser storage fails, chatbot should still work normally.
    }
  };

  const saveChatItem = (item) => {
    const history = readChatHistory();

    history.push({
      ...item,
      time: new Date().toISOString(),
    });

    writeChatHistory(history);
  };

  const clearChatHistory = () => {
    sessionStorage.removeItem(getChatStorageKey());
  };

  const setOpen = (open) => {
    root.classList.toggle("is-open", open);
    panel.setAttribute("aria-hidden", open ? "false" : "true");
    toggleButton.setAttribute(
      "aria-label",
      open ? "Close Ridex chatbot" : "Open Ridex chatbot",
    );

    if (open) {
      input.focus();
    }
  };

  const scrollToBottom = () => {
    messages.scrollTop = messages.scrollHeight;
  };

  const addMessage = (text, sender = "bot", shouldSave = true) => {
    const bubble = document.createElement("div");
    bubble.className = `ridex-chatbot__message ridex-chatbot__message--${sender}`;
    bubble.textContent = text;
    messages.appendChild(bubble);
    scrollToBottom();

    if (shouldSave) {
      saveChatItem({
        kind: "message",
        sender,
        text,
      });
    }

    return bubble;
  };

  const getCurrentBookingValue = (name) => {
    const params = new URLSearchParams(window.location.search);
    return params.get(name) || "";
  };

  const getVehicleType = (vehicle) => {
    const type = String(vehicle.type || vehicle.vehicle_type || "cars").toLowerCase();
    return ["cars", "bikes", "luxury"].includes(type) ? type : "cars";
  };

  const buildBookingRedirectUrl = (vehicle) => {
    const params = new URLSearchParams();

    params.set("page", "booking-engine");
    params.set("flow_start", "1");
    params.set("vehicle_id", String(vehicle.id || "0"));
    params.set("vehicle_type", getVehicleType(vehicle));

    [
      "pickup-location",
      "return-location",
      "pickup-date",
      "return-date",
      "pickup-time",
      "return-time",
    ].forEach((key) => {
      const value = getCurrentBookingValue(key);
      if (value !== "") {
        params.set(key, value);
      }
    });

    return `index.php?${params.toString()}`;
  };

  const setHiddenInputValue = (selector, value) => {
    const inputNode = document.querySelector(selector);

    if (inputNode instanceof HTMLInputElement) {
      inputNode.value = String(value || "").trim();
    }
  };

  const fillBookingConfirmModal = (vehicle) => {
    setHiddenInputValue("[data-booking-confirm-vehicle-id]", vehicle.id || "0");
    setHiddenInputValue("[data-booking-confirm-vehicle-type]", getVehicleType(vehicle));
    setHiddenInputValue("[data-booking-confirm-pickup-location]", getCurrentBookingValue("pickup-location"));
    setHiddenInputValue("[data-booking-confirm-return-location]", getCurrentBookingValue("return-location"));
    setHiddenInputValue("[data-booking-confirm-pickup-date]", getCurrentBookingValue("pickup-date"));
    setHiddenInputValue("[data-booking-confirm-return-date]", getCurrentBookingValue("return-date"));
    setHiddenInputValue("[data-booking-confirm-pickup-time]", getCurrentBookingValue("pickup-time"));
    setHiddenInputValue("[data-booking-confirm-return-time]", getCurrentBookingValue("return-time"));
  };

  const openModalManually = (modalId) => {
    const modal = document.getElementById(modalId);

    if (!(modal instanceof HTMLElement)) {
      return false;
    }

    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    modal.classList.add("is-open");
    document.body.classList.add("menu-modal-open");

    return true;
  };

  const openLoginModal = (redirectUrl) => {
    const redirectInputs = Array.from(
      document.querySelectorAll("[data-user-post-auth-redirect]"),
    );

    redirectInputs.forEach((inputNode) => {
      if (inputNode instanceof HTMLInputElement) {
        inputNode.value = redirectUrl;
      }
    });

    const loginTrigger = document.querySelector("[data-booking-login-modal-trigger='true']");

    if (loginTrigger instanceof HTMLElement) {
      loginTrigger.click();
      return true;
    }

    return openModalManually("user-login-modal");
  };

  const openBookingConfirmModal = (vehicle) => {
    const redirectUrl = buildBookingRedirectUrl(vehicle);

    if (document.body.dataset.userAuthenticated !== "1") {
      setOpen(false);

      if (!openLoginModal(redirectUrl)) {
        addMessage("Please log in first, then click Book Now again.", "bot");
      }

      return;
    }

    fillBookingConfirmModal(vehicle);
    setOpen(false);

    // First try the existing Ridex modal system.
    const trigger = document.querySelector(
      "[data-ridex-chatbot-booking-modal-trigger][data-modal-target='user-booking-confirm-modal']",
    );

    if (trigger instanceof HTMLElement) {
      trigger.click();
    }

    // Safety fallback: if the normal modal script did not open it, open it manually.
    window.setTimeout(() => {
      const modal = document.getElementById("user-booking-confirm-modal");

      const isAlreadyOpen =
        modal instanceof HTMLElement &&
        !modal.hidden &&
        modal.getAttribute("aria-hidden") === "false";

      if (!isAlreadyOpen) {
        openModalManually("user-booking-confirm-modal");
      }
    }, 50);
  };

  const addVehicleCards = (vehicles, shouldSave = true) => {
    if (!Array.isArray(vehicles) || vehicles.length === 0) {
      return;
    }

    const list = document.createElement("div");
    list.className = "ridex-chatbot__cards";

    vehicles.forEach((vehicle) => {
      const card = document.createElement("article");
      card.className = "ridex-chatbot__card";

      const image = document.createElement("img");
      image.className = "ridex-chatbot__card-image";
      image.src = vehicle.image_path || "images/vehicle-feature.png";
      image.alt = vehicle.full_name || vehicle.name || "Vehicle";

      image.onerror = () => {
        image.onerror = null;
        image.src = "images/vehicle-feature.png";
      };

      const body = document.createElement("div");
      body.className = "ridex-chatbot__card-body";

      const name = document.createElement("h3");
      name.className = "ridex-chatbot__card-title";
      name.textContent = vehicle.name || vehicle.full_name || "Vehicle";

      const meta = document.createElement("p");
      meta.className = "ridex-chatbot__card-meta";

      const seats = Number(vehicle.seats || 0) > 0
        ? `${vehicle.seats} seats`
        : "Seats N/A";

      meta.textContent = `NRs ${vehicle.price_per_day || 0}/day • ${seats} • ${vehicle.fuel || "Fuel N/A"}`;

      const score = document.createElement("p");
      score.className = "ridex-chatbot__card-score";
      score.textContent = `Score ${vehicle.score || 0}`;

      const actions = document.createElement("div");
      actions.className = "ridex-chatbot__card-actions";

      const link = document.createElement("a");
      link.className = "ridex-chatbot__card-link";
      link.href = vehicle.detail_url || "index.php?page=vehicles";
      link.textContent = "View details";

      const bookButton = document.createElement("button");
      bookButton.className = "ridex-chatbot__card-book";
      bookButton.type = "button";
      bookButton.textContent = "Book Now";
      bookButton.addEventListener("click", () => openBookingConfirmModal(vehicle));

      actions.append(link, bookButton);
      body.append(name, meta, score, actions);
      card.append(image, body);
      list.appendChild(card);
    });

    messages.appendChild(list);
    scrollToBottom();

    if (shouldSave) {
      saveChatItem({
        kind: "vehicles",
        vehicles,
      });
    }
  };

  const loadChatHistory = () => {
    const history = readChatHistory();

    if (!Array.isArray(history) || history.length === 0) {
      return;
    }

    messages.innerHTML = "";

    history.forEach((item) => {
      if (item.kind === "message") {
        addMessage(item.text || "", item.sender || "bot", false);
      }

      if (item.kind === "vehicles") {
        addVehicleCards(item.vehicles || [], false);
      }
    });

    scrollToBottom();
  };

  toggleButton.addEventListener("click", () => {
    setOpen(!root.classList.contains("is-open"));
  });

  closeButton.addEventListener("click", () => {
    setOpen(false);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const message = input.value.trim();

    if (message === "") {
      return;
    }

    addMessage(message, "user");

    input.value = "";
    input.disabled = true;

    const loadingBubble = addMessage("Thinking...", "bot", false);

    try {
      const response = await fetch("ajax/ridex-chatbot.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ message }),
      });

      const data = await response.json();

      loadingBubble.remove();

      if (!response.ok || !data.ok) {
        addMessage(
          data.reply || "Sorry, I could not process that message right now.",
          "bot",
        );
      } else {
        addMessage(data.reply || "Here is what I found.", "bot");
        addVehicleCards(data.recommendations || []);
      }
    } catch (error) {
      loadingBubble.remove();

      addMessage(
        "Sorry, the chatbot is offline right now. You can still browse vehicles from the homepage.",
        "bot",
      );
    } finally {
      input.disabled = false;
      input.focus();
    }
  });

  /*
    Important:
    This runs once when the page loads.

    It checks whether the user changed from:
    - guest to logged-in
    - logged-in to guest

    If that happened, old chatbot history is removed.
  */
  initializeChatSession();

  // Load previous chat messages only for the current active session.
  loadChatHistory();

  /*
    Optional testing command:
    If you ever want to clear chatbot history manually, open browser console and run:

    sessionStorage.clear();
  */
})();