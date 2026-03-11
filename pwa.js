console.log("✅ pwa.js loaded");

if (!("serviceWorker" in navigator)) {
  console.log("❌ Service workers not supported in this browser");
} else {
  window.addEventListener("load", () => {
    console.log("✅ window loaded — registering sw.js...");

    navigator.serviceWorker
      .register("./sw.js")
      .then((reg) => console.log("✅ Service Worker registered:", reg.scope))
      .catch((err) => console.error("❌ Service Worker registration failed:", err));
  });
}
