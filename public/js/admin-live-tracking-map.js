/**
 * Purpose: Simulate an interactive admin tracking map with pan/zoom controls.
 * Website Section: Admin GPS Tracking.
 */

(function () {
  "use strict";

  var mapRoots = Array.from(document.querySelectorAll("[data-live-map-root]"));
  if (!mapRoots.length) {
    return;
  }

  var clamp = function (value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
  };

  var initializeInteractiveMap = function (mapRoot) {
    if (!(mapRoot instanceof HTMLElement)) {
      return;
    }

    var viewport = mapRoot.querySelector("[data-live-map-viewport]");
    var stage = mapRoot.querySelector("[data-live-map-stage]");
    if (!(viewport instanceof HTMLElement) || !(stage instanceof HTMLElement)) {
      return;
    }

    var zoomInButton = mapRoot.querySelector("[data-live-map-zoom-in]");
    var zoomOutButton = mapRoot.querySelector("[data-live-map-zoom-out]");
    var resetButton = mapRoot.querySelector("[data-live-map-reset]");

    var state = {
      scale: 1,
      minScale: 0.7,
      maxScale: 3,
      x: 0,
      y: 0,
      dragging: false,
      movedDuringDrag: false,
      pointerId: null,
      pointerOffsetX: 0,
      pointerOffsetY: 0,
    };

    var getMetrics = function () {
      return {
        viewportWidth: Math.max(1, viewport.clientWidth),
        viewportHeight: Math.max(1, viewport.clientHeight),
        stageWidth: Math.max(1, stage.offsetWidth * state.scale),
        stageHeight: Math.max(1, stage.offsetHeight * state.scale),
      };
    };

    var getTranslationBounds = function () {
      var metrics = getMetrics();
      var minX = Math.min(0, metrics.viewportWidth - metrics.stageWidth);
      var maxX = 0;
      var minY = Math.min(0, metrics.viewportHeight - metrics.stageHeight);
      var maxY = 0;

      if (metrics.stageWidth <= metrics.viewportWidth) {
        var centeredX = (metrics.viewportWidth - metrics.stageWidth) / 2;
        minX = centeredX;
        maxX = centeredX;
      }

      if (metrics.stageHeight <= metrics.viewportHeight) {
        var centeredY = (metrics.viewportHeight - metrics.stageHeight) / 2;
        minY = centeredY;
        maxY = centeredY;
      }

      return {
        minX: minX,
        maxX: maxX,
        minY: minY,
        maxY: maxY,
      };
    };

    var applyTransform = function () {
      var bounds = getTranslationBounds();
      state.x = clamp(state.x, bounds.minX, bounds.maxX);
      state.y = clamp(state.y, bounds.minY, bounds.maxY);

      stage.style.transform =
        "translate(" +
        state.x.toFixed(2) +
        "px, " +
        state.y.toFixed(2) +
        "px) scale(" +
        state.scale.toFixed(4) +
        ")";

      mapRoot.setAttribute("data-map-zoom", state.scale.toFixed(2));
    };

    var centerStage = function (targetScale) {
      if (typeof targetScale === "number" && Number.isFinite(targetScale)) {
        state.scale = clamp(targetScale, state.minScale, state.maxScale);
      }

      var metrics = getMetrics();
      state.x = (metrics.viewportWidth - metrics.stageWidth) / 2;
      state.y = (metrics.viewportHeight - metrics.stageHeight) / 2;
      applyTransform();
    };

    var zoomAtClientPoint = function (clientX, clientY, zoomFactor) {
      var oldScale = state.scale;
      var nextScale = clamp(
        oldScale * zoomFactor,
        state.minScale,
        state.maxScale,
      );
      if (Math.abs(nextScale - oldScale) < 0.0001) {
        return;
      }

      var viewportRect = viewport.getBoundingClientRect();
      var anchorX = clientX - viewportRect.left;
      var anchorY = clientY - viewportRect.top;

      var worldX = (anchorX - state.x) / oldScale;
      var worldY = (anchorY - state.y) / oldScale;

      state.scale = nextScale;
      state.x = anchorX - worldX * state.scale;
      state.y = anchorY - worldY * state.scale;
      applyTransform();
    };

    var zoomAroundCenter = function (zoomFactor) {
      var viewportRect = viewport.getBoundingClientRect();
      var centerX = viewportRect.left + viewportRect.width / 2;
      var centerY = viewportRect.top + viewportRect.height / 2;
      zoomAtClientPoint(centerX, centerY, zoomFactor);
    };

    var onWheel = function (event) {
      event.preventDefault();
      var zoomFactor = event.deltaY < 0 ? 1.12 : 1 / 1.12;
      zoomAtClientPoint(event.clientX, event.clientY, zoomFactor);
    };

    var onPointerDown = function (event) {
      var isMousePointer = event.pointerType === "mouse";
      if (isMousePointer && event.button !== 0) {
        return;
      }

      state.dragging = true;
      state.movedDuringDrag = false;
      state.pointerId = event.pointerId;
      state.pointerOffsetX = event.clientX - state.x;
      state.pointerOffsetY = event.clientY - state.y;
      viewport.classList.add("is-dragging");

      if (typeof viewport.setPointerCapture === "function") {
        try {
          viewport.setPointerCapture(event.pointerId);
        } catch (_captureError) {
          // Ignore pointer capture issues and continue with regular drag behavior.
        }
      }
    };

    var onPointerMove = function (event) {
      if (!state.dragging || event.pointerId !== state.pointerId) {
        return;
      }

      var nextX = event.clientX - state.pointerOffsetX;
      var nextY = event.clientY - state.pointerOffsetY;

      if (
        !state.movedDuringDrag &&
        (Math.abs(nextX - state.x) > 1 || Math.abs(nextY - state.y) > 1)
      ) {
        state.movedDuringDrag = true;
      }

      state.x = nextX;
      state.y = nextY;
      applyTransform();
    };

    var endDrag = function (event) {
      if (!state.dragging) {
        return;
      }

      if (
        event &&
        state.pointerId !== null &&
        event.pointerId !== state.pointerId
      ) {
        return;
      }

      state.dragging = false;
      state.pointerId = null;
      viewport.classList.remove("is-dragging");
    };

    var cancelMarkerClickAfterDrag = function (event) {
      if (!state.movedDuringDrag) {
        return;
      }

      if (!(event.target instanceof Element)) {
        return;
      }

      var marker = event.target.closest(".admin-live-tracking__marker");
      if (!marker) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    };

    viewport.addEventListener("wheel", onWheel, { passive: false });
    viewport.addEventListener("pointerdown", onPointerDown);
    viewport.addEventListener("pointermove", onPointerMove);
    viewport.addEventListener("pointerup", endDrag);
    viewport.addEventListener("pointercancel", endDrag);
    viewport.addEventListener("lostpointercapture", endDrag);
    viewport.addEventListener("click", cancelMarkerClickAfterDrag, true);

    if (zoomInButton instanceof HTMLButtonElement) {
      zoomInButton.addEventListener("click", function () {
        zoomAroundCenter(1.15);
      });
    }

    if (zoomOutButton instanceof HTMLButtonElement) {
      zoomOutButton.addEventListener("click", function () {
        zoomAroundCenter(1 / 1.15);
      });
    }

    if (resetButton instanceof HTMLButtonElement) {
      resetButton.addEventListener("click", function () {
        centerStage(1);
      });
    }

    window.addEventListener("resize", function () {
      applyTransform();
    });

    mapRoot.classList.add("is-interactive");
    centerStage(1);
  };

  mapRoots.forEach(initializeInteractiveMap);
})();
