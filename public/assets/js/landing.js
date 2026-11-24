(function () {
  "use strict";

  var scene = document.getElementById("bottleScene");
  var rig = document.getElementById("bottleRig");
  var gloss = document.getElementById("bottleGloss");
  var shadow = document.getElementById("bottleShadow");
  if (!scene || !rig) {
    return;
  }

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var pointerX = 0;
  var pointerY = 0;
  var targetX = 0;
  var targetY = 0;
  var start = performance.now();

  function onPointer(event) {
    var point = event.touches && event.touches[0] ? event.touches[0] : event;
    var rect = scene.getBoundingClientRect();
    var x = (point.clientX - rect.left) / rect.width;
    var y = (point.clientY - rect.top) / rect.height;
    targetX = Math.max(-1, Math.min(1, (x - 0.5) * 2));
    targetY = Math.max(-1, Math.min(1, (y - 0.5) * 2));
  }

  scene.addEventListener("pointermove", onPointer, { passive: true });
  scene.addEventListener("pointerleave", function () {
    targetX = 0;
    targetY = 0;
  });

  function frame(now) {
    var t = (now - start) / 1000;
    pointerX += (targetX - pointerX) * 0.08;
    pointerY += (targetY - pointerY) * 0.08;

    // Studio turntable: smooth left/right orbit that keeps the bottle solid and readable.
    var turn = reduceMotion ? 0 : Math.sin(t * 0.7) * 34;
    var drift = reduceMotion ? 0 : Math.sin(t * 0.35) * 6;
    var yaw = turn + drift + pointerX * 18;
    var pitch = -4 + pointerY * -8 + (reduceMotion ? 0 : Math.sin(t * 0.9) * 2.5);
    var roll = pointerX * -3;
    var lift = reduceMotion ? 0 : Math.sin(t * 1.25) * 8;
    var rad = (yaw * Math.PI) / 180;

    // Mild cylinder foreshortening — enough depth, never paper-thin.
    var scaleX = 0.88 + Math.abs(Math.cos(rad)) * 0.12;
    var scaleZ = 1;

    rig.style.transform =
      "translate3d(0," + (-lift).toFixed(2) + "px,40px)" +
      " rotateX(" + pitch.toFixed(2) + "deg)" +
      " rotateY(" + yaw.toFixed(2) + "deg)" +
      " rotateZ(" + roll.toFixed(2) + "deg)" +
      " scaleX(" + scaleX.toFixed(3) + ")" +
      " scaleZ(" + scaleZ + ")";

    if (gloss) {
      var shine = yaw * 1.15;
      gloss.style.transform = "translateX(" + shine.toFixed(2) + "%) skewY(-4deg)";
      gloss.style.opacity = (0.45 + Math.abs(Math.cos(rad)) * 0.4).toFixed(3);
    }

    if (shadow) {
      var squash = 0.9 + Math.abs(Math.cos(rad)) * 0.18;
      shadow.style.transform =
        "translateX(" + (yaw * 0.35 + pointerX * 8).toFixed(2) + "px) scaleX(" + squash.toFixed(3) + ")";
      shadow.style.opacity = (0.28 + Math.abs(Math.cos(rad)) * 0.12).toFixed(3);
    }

    requestAnimationFrame(frame);
  }

  requestAnimationFrame(frame);
})();
