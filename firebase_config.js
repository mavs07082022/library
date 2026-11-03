// firebase_config.js - Firebase AI Logic with reCAPTCHA Enterprise
// Load this in <head> before other scripts

// ============================================
// APP CHECK - LOCAL DEVELOPMENT DEBUG TOKEN
// ============================================
if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') {
    self.FIREBASE_APPCHECK_DEBUG_TOKEN = "dbb33a1b-9d8d-4588-a735-ee4b2fba1cf9";
}

// ============================================
// FIREBASE CONFIGURATION
// ============================================
const firebaseConfig = {
  apiKey: "AIzaSyCbfDydzzsjOIOP6jeoDbyL3MmGHDbI07k",
  authDomain: "saac-library.firebaseapp.com",
  projectId: "saac-library",
  storageBucket: "saac-library.firebasestorage.app",
  messagingSenderId: "140978710101",
  appId: "1:140978710101:web:77f9d5876e896ada0f96dc"
};

const RECAPTCHA_ENTERPRISE_SITE_KEY = "6Le2wLMtAAAAAE2TJSbbtQt39QsI7x-vN1lxMbT1";

// ============================================
// FIREBASE STATE
// ============================================
let firebaseApp = null;
let firebaseAI = null;
let geminiModel = null;
let initPromise = null;

// ============================================
// INITIALIZE FIREBASE (memoized)
// ============================================
async function initializeFirebase() {
  if (geminiModel) return { firebaseApp, firebaseAI, geminiModel };
  if (initPromise) return initPromise;

  initPromise = (async () => {
    try {
      const { initializeApp } = await import('https://www.gstatic.com/firebasejs/12.12.1/firebase-app.js');
      const { initializeAppCheck, ReCaptchaEnterpriseProvider } = await import('https://www.gstatic.com/firebasejs/12.12.1/firebase-app-check.js');
      const { getAI, getGenerativeModel, GoogleAIBackend } = await import('https://www.gstatic.com/firebasejs/12.12.1/firebase-ai.js');

      firebaseApp = initializeApp(firebaseConfig);

      const isLocalDev = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
      if (isLocalDev) {
        console.log('🔧 App Check: Using debug token (local development)');
      } else {
        initializeAppCheck(firebaseApp, {
          provider: new ReCaptchaEnterpriseProvider(RECAPTCHA_ENTERPRISE_SITE_KEY),
          isTokenAutoRefreshEnabled: true
        });
        console.log('🔒 App Check: Using reCAPTCHA Enterprise (production)');
      }

      firebaseAI = getAI(firebaseApp, { backend: new GoogleAIBackend() });
      geminiModel = getGenerativeModel(firebaseAI, { model: "gemini-3.1-flash-lite" });

      console.log('✅ Firebase AI Logic ready');
      return { firebaseApp, firebaseAI, geminiModel };
    } catch (err) {
      console.error('❌ Firebase init failed:', err);
      initPromise = null; // allow retry
      throw err;
    }
  })();

  return initPromise;
}

// ============================================
// AI HELPERS (used by ai_functions.js)
// ============================================

async function callGemini(prompt, opts = {}) {
  await initializeFirebase();
  const result = await geminiModel.generateContent(prompt);
  const text = result.response.text();
  if (opts.json) {
    // Strip markdown code fences if present
    const cleaned = text.replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/```$/i, '').trim();
    try { return JSON.parse(cleaned); } catch { return null; }
  }
  return text;
}

async function firebaseSemanticSearch(query, books, limit = 20) {
  try {
    const compact = books.map(b => ({
      id: b.id, title: b.title, author: b.author,
      description: (b.description || '').slice(0, 200),
      category: b.categories?.name || b.category || '',
      keywords: b.keywords || ''
    }));

    const prompt = `You are a library catalog search assistant. Given the user query below and the catalog of books (JSON), return ONLY a JSON array of book IDs ranked by semantic relevance to the query. Max ${limit} results. No prose, no code fences.

User query: "${query}"

Catalog:
${JSON.stringify(compact)}

Return JSON array of IDs only, e.g. ["uuid1","uuid2"]. If nothing matches, return [].`;

    const ids = await callGemini(prompt, { json: true });
    if (!Array.isArray(ids)) return [];
    const byId = new Map(books.map(b => [String(b.id), b]));
    return ids.map(id => byId.get(String(id))).filter(Boolean);
  } catch (err) {
    console.warn('Firebase semanticSearch error:', err);
    return [];
  }
}

async function firebasePredictSearch(query, gradeLevel, subjects, history) {
  try {
    const prompt = `You are a predictive search assistant for a school library. Given the partial query "${query}", suggest up to 5 likely full search queries a ${gradeLevel} student with subjects [${subjects.join(', ')}] and recent searches [${history.slice(0,5).join(', ')}] might intend. Return ONLY a JSON array of strings.`;
    const arr = await callGemini(prompt, { json: true });
    return Array.isArray(arr) ? arr : [];
  } catch (err) {
    console.warn('Firebase predictSearch error:', err);
    return [];
  }
}

async function firebaseAnalyzeSession(queries, clicks, abandoned) {
  try {
    const prompt = `A library user made these searches: ${JSON.stringify(queries)}. Clicks per search: ${JSON.stringify(clicks)}. Abandoned searches: ${JSON.stringify(abandoned)}. Detect frustration. Return ONLY a JSON object: {"frustration_detected": boolean, "frustration_score": 0-100, "reasons": string[], "suggestions": string[]}.`;
    const res = await callGemini(prompt, { json: true });
    return res || { frustration_detected: false, frustration_score: 0, reasons: [], suggestions: [] };
  } catch (err) {
    console.warn('Firebase analyzeSession error:', err);
    return { frustration_detected: false, frustration_score: 0, reasons: [], suggestions: [] };
  }
}

async function firebaseClassifyBook(title, description, categories) {
  try {
    const prompt = `Classify this book into one of these categories: ${JSON.stringify(categories.map(c => c.name))}.
Book title: "${title}"
Description: "${description}"
Return ONLY a JSON object: {"category": "exact category name", "confidence": 0-1}.`;
    return await callGemini(prompt, { json: true });
  } catch (err) {
    console.warn('Firebase classifyBook error:', err);
    return null;
  }
}

// ============================================
// EXPORT TO WINDOW
// ============================================
window.firebaseServices = {
  initialize: initializeFirebase,
  get model() { return geminiModel; },
  get isReady() { return geminiModel !== null; },
  semanticSearch: firebaseSemanticSearch,
  predictSearch: firebasePredictSearch,
  analyzeSession: firebaseAnalyzeSession,
  classifyBook: firebaseClassifyBook,
};

// ============================================
// AUTO-INIT on page load (this is the missing piece)
// ============================================
if (typeof window !== 'undefined') {
  // Fire and forget — don't block anything
  initializeFirebase().catch(err => console.warn('Firebase auto-init failed:', err));
}