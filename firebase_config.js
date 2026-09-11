// firebase_config.js - Firebase AI Logic with reCAPTCHA Enterprise
// Load this in <head> before other scripts

// ============================================
// APP CHECK - LOCAL DEVELOPMENT DEBUG TOKEN
// ============================================
// This token is ONLY used when running on localhost.
// In production, reCAPTCHA Enterprise takes over.
if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') {
    self.FIREBASE_APPCHECK_DEBUG_TOKEN = "dbb33a1b-9d8d-4588-a735-ee4b2fba1cf9";
}

// ============================================
// FIREBASE CONFIGURATION
// Replace these with your actual values from Firebase Console
// ============================================
const firebaseConfig = {
  apiKey: "AIzaSyCbfDydzzsjOIOP6jeoDbyL3MmGHDbI07k",
  authDomain: "saac-library.firebaseapp.com",
  projectId: "saac-library",
  storageBucket: "saac-library.firebasestorage.app",
  messagingSenderId: "140978710101",
  appId: "1:140978710101:web:77f9d5876e896ada0f96dc"
};

// ============================================
// RECAPTCHA ENTERPRISE SITE KEY (PRODUCTION)
// Copy from Google Cloud Console → Security → reCAPTCHA → Key details
// ============================================
const RECAPTCHA_ENTERPRISE_SITE_KEY = "6Le2wLMtAAAAAE2TJSbbtQt39QsI7x-vN1lxMbT1";

// ============================================
// FIREBASE STATE
// ============================================
let firebaseApp = null;
let firebaseAI = null;
let geminiModel = null;

// ============================================
// INITIALIZE FIREBASE
// ============================================
async function initializeFirebase() {
  if (geminiModel) return { firebaseApp, firebaseAI, geminiModel };

  // Load Firebase SDK modules (v12.12.1 - required for AI Logic)
  const { initializeApp } = await import('https://www.gstatic.com/firebasejs/12.12.1/firebase-app.js');
  const { initializeAppCheck, ReCaptchaEnterpriseProvider } = await import('https://www.gstatic.com/firebasejs/12.12.1/firebase-app-check.js');
  const { getAI, getGenerativeModel, GoogleAIBackend } = await import('https://www.gstatic.com/firebasejs/12.12.1/firebase-ai.js');

  // Initialize Firebase App
  firebaseApp = initializeApp(firebaseConfig);

  // ============================================
  // APP CHECK INITIALIZATION
  // ============================================
  const isLocalDev = location.hostname === 'localhost' || location.hostname === '127.0.0.1';

  if (isLocalDev) {
    // Local development: debug token was set at the top of this file.
    // Firebase SDK will automatically use it.
    console.log('🔧 App Check: Using debug token (local development)');
  } else {
    // Production: use reCAPTCHA Enterprise
    initializeAppCheck(firebaseApp, {
      provider: new ReCaptchaEnterpriseProvider(RECAPTCHA_ENTERPRISE_SITE_KEY),
      isTokenAutoRefreshEnabled: true
    });
    console.log('🔒 App Check: Using reCAPTCHA Enterprise (production)');
  }

  // ============================================
  // FIREBASE AI LOGIC INITIALIZATION
  // ============================================
  firebaseAI = getAI(firebaseApp, { backend: new GoogleAIBackend() });
  geminiModel = getGenerativeModel(firebaseAI, { model: "gemini-3.1-flash-lite" });

  console.log('✅ Firebase AI Logic ready');
  return { firebaseApp, firebaseAI, geminiModel };
}

// ============================================
// EXPORT TO WINDOW
// ============================================
window.firebaseServices = {
  initialize: initializeFirebase,
  get model() { return geminiModel; },
  get isReady() { return geminiModel !== null; }
};