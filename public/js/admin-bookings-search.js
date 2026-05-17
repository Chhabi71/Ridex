/**
 * Purpose: Live AJAX filter for admin all-bookings table.
 * Website Section: Admin Booking Management.
*/

(function () {
  const searchInput = document.querySelector(
    "[data-admin-bookings-search-input]",
  );
  const tableBody = document.querySelector("[data-admin-bookings-table-body]");
  const searchShell = searchInput?.closest(".admin-bookings__search");

  if (!(searchInput instanceof HTMLInputElement)) {
    return;
  }

  if (!(tableBody instanceof HTMLElement)) {
    return;
  }

  if (!(searchShell instanceof HTMLElement)) {
    return;
  }

  const bookingRows = Array.from(
    tableBody.querySelectorAll("tr[data-booking-row-id]"),
  );

  if (bookingRows.length === 0) {
    return;
  }

  const rowsByBookingId = new Map();
  bookingRows.forEach((row) => {
    const bookingId = String(
      row.getAttribute("data-booking-row-id") || "",
    ).trim();
    if (bookingId !== "") {
      rowsByBookingId.set(bookingId, row);
    }
  });

  if (rowsByBookingId.size === 0) {
    return;
  }

  const normalizeSearchValue = (rawValue) =>
    String(rawValue || "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim();

  const searchIndexByBookingId = new Map();
  const suggestionMetaByBookingId = new Map();

  rowsByBookingId.forEach((rowNode, bookingId) => {
    searchIndexByBookingId.set(
      bookingId,
      normalizeSearchValue(rowNode.textContent),
    );

    const cells = rowNode.querySelectorAll("td");
    const bookingLabel = String(cells[0]?.textContent || "").trim() || "#N/A";
    const customerLabel =
      String(cells[1]?.textContent || "").trim() || "Unknown";
    const vehicleLabel =
      String(cells[2]?.textContent || "").trim() || "Vehicle";
    const viewButton = rowNode.querySelector(
      ".admin-bookings__view-btn[data-modal-target='admin-booking-read-modal']",
    );

    suggestionMetaByBookingId.set(bookingId, {
      bookingLabel,
      customerLabel,
      vehicleLabel,
      viewButton,
      rowNode,
    });
  });

  const table = document.querySelector(".admin-bookings__table");
  const headerCells = table ? table.querySelectorAll("thead th") : [];
  const columnCount = headerCells.length > 0 ? headerCells.length : 8;

  const emptySearchRow = document.createElement("tr");
  emptySearchRow.hidden = true;
  emptySearchRow.innerHTML =
    '<td class="admin-bookings__empty" colspan="' +
    String(columnCount) +
    '">No bookings match your search.</td>';
  tableBody.appendChild(emptySearchRow);

  const suggestionsPanel = document.createElement("div");
  suggestionsPanel.className = "admin-bookings__suggestions";
  suggestionsPanel.hidden = true;
  searchShell.appendChild(suggestionsPanel);

  let debounceTimer = null;
  let activeSuggestionId = "";

  const setBusyState = (isBusy) => {
    if (isBusy) {
      tableBody.setAttribute("aria-busy", "true");
    } else {
      tableBody.removeAttribute("aria-busy");
    }
  };

  const applyFilter = (visibleIdSet) => {
    let visibleCount = 0;

    rowsByBookingId.forEach((rowNode, bookingId) => {
      const shouldShow =
        visibleIdSet === null ||
        !(visibleIdSet instanceof Set) ||
        visibleIdSet.has(bookingId);

      rowNode.hidden = !shouldShow;
      if (shouldShow) {
        visibleCount += 1;
      }
    });

    emptySearchRow.hidden = visibleCount > 0;
  };

  const getLocalMatchedIds = (normalizedQuery) => {
    if (normalizedQuery === "") {
      return [];
    }

    const matchedIds = [];
    searchIndexByBookingId.forEach((searchIndexText, bookingId) => {
      if (searchIndexText.includes(normalizedQuery)) {
        matchedIds.push(bookingId);
      }
    });

    return matchedIds;
  };

  const hideSuggestions = () => {
    suggestionsPanel.hidden = true;
    suggestionsPanel.innerHTML = "";
    activeSuggestionId = "";
  };

  const renderSuggestions = (matchedIds, normalizedQuery) => {
    if (!Array.isArray(matchedIds) || normalizedQuery === "") {
      hideSuggestions();
      return;
    }

    const topMatches = matchedIds.slice(0, 8);
    if (topMatches.length === 0) {
      hideSuggestions();
      return;
    }

    suggestionsPanel.innerHTML = "";
    activeSuggestionId = "";

    const fragment = document.createDocumentFragment();
    topMatches.forEach((bookingId, index) => {
      const suggestionMeta = suggestionMetaByBookingId.get(bookingId);
      if (!suggestionMeta) {
        return;
      }

      const button = document.createElement("button");
      button.type = "button";
      button.className = "admin-bookings__suggestion";
      button.setAttribute("data-suggestion-booking-id", bookingId);

      if (index === 0) {
        button.classList.add("is-active");
        activeSuggestionId = bookingId;
      }

      const bookingIdNode = document.createElement("span");
      bookingIdNode.className = "admin-bookings__suggestion-id";
      bookingIdNode.textContent = suggestionMeta.bookingLabel;

      const bookingMetaNode = document.createElement("span");
      bookingMetaNode.className = "admin-bookings__suggestion-meta";
      bookingMetaNode.textContent =
        suggestionMeta.customerLabel + " - " + suggestionMeta.vehicleLabel;

      button.appendChild(bookingIdNode);
      button.appendChild(bookingMetaNode);
      fragment.appendChild(button);
    });

    suggestionsPanel.appendChild(fragment);
    suggestionsPanel.hidden = suggestionsPanel.children.length === 0;
  };

  const setActiveSuggestion = (bookingId) => {
    activeSuggestionId = String(bookingId || "").trim();

    const suggestionButtons = Array.from(
      suggestionsPanel.querySelectorAll(".admin-bookings__suggestion"),
    );
    suggestionButtons.forEach((button) => {
      const buttonBookingId = String(
        button.getAttribute("data-suggestion-booking-id") || "",
      ).trim();
      button.classList.toggle(
        "is-active",
        buttonBookingId === activeSuggestionId,
      );
    });
  };

  const openBookingFromSuggestion = (bookingId) => {
    const normalizedBookingId = String(bookingId || "").trim();
    if (normalizedBookingId === "") {
      return;
    }

    const suggestionMeta = suggestionMetaByBookingId.get(normalizedBookingId);
    if (!suggestionMeta) {
      return;
    }

    applyFilter(new Set([normalizedBookingId]));
    searchInput.value = suggestionMeta.bookingLabel;
    hideSuggestions();

    if (suggestionMeta.viewButton instanceof HTMLElement) {
      suggestionMeta.viewButton.click();
    }
  };

  const filterRowsAndSuggestions = (rawQuery) => {
    const normalizedQuery = normalizeSearchValue(rawQuery);

    if (normalizedQuery === "") {
      applyFilter(null);
      hideSuggestions();
      return;
    }

    const matchedIds = getLocalMatchedIds(normalizedQuery);
    applyFilter(new Set(matchedIds));
    renderSuggestions(matchedIds, normalizedQuery);
  };

  const moveActiveSuggestion = (direction) => {
    const suggestionButtons = Array.from(
      suggestionsPanel.querySelectorAll(".admin-bookings__suggestion"),
    );

    if (suggestionButtons.length === 0) {
      return;
    }

    const currentIndex = suggestionButtons.findIndex((button) => {
      const buttonBookingId = String(
        button.getAttribute("data-suggestion-booking-id") || "",
      ).trim();
      return buttonBookingId === activeSuggestionId;
    });

    const safeIndex = currentIndex >= 0 ? currentIndex : 0;
    let nextIndex = safeIndex + direction;
    if (nextIndex < 0) {
      nextIndex = suggestionButtons.length - 1;
    } else if (nextIndex >= suggestionButtons.length) {
      nextIndex = 0;
    }

    const nextButton = suggestionButtons[nextIndex];
    if (!(nextButton instanceof HTMLButtonElement)) {
      return;
    }

    const nextBookingId = String(
      nextButton.getAttribute("data-suggestion-booking-id") || "",
    ).trim();
    setActiveSuggestion(nextBookingId);
  };

  searchInput.addEventListener("input", () => {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => {
      setBusyState(true);
      filterRowsAndSuggestions(searchInput.value);
      setBusyState(false);
    }, 90);
  });

  searchInput.addEventListener("keydown", (event) => {
    if (suggestionsPanel.hidden) {
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      moveActiveSuggestion(1);
      return;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      moveActiveSuggestion(-1);
      return;
    }

    if (event.key === "Enter") {
      if (activeSuggestionId !== "") {
        event.preventDefault();
        openBookingFromSuggestion(activeSuggestionId);
      }
      return;
    }

    if (event.key === "Escape") {
      hideSuggestions();
    }
  });

  suggestionsPanel.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    const suggestionButton = target.closest(".admin-bookings__suggestion");
    if (!(suggestionButton instanceof HTMLButtonElement)) {
      return;
    }

    const bookingId = String(
      suggestionButton.getAttribute("data-suggestion-booking-id") || "",
    ).trim();
    if (bookingId === "") {
      return;
    }

    openBookingFromSuggestion(bookingId);
  });

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (!searchShell.contains(target)) {
      hideSuggestions();
    }
  });
})();
