// ai_functions.js - Firebase AI Functions for SAAC Library
// Replaces all Python NLP functionality with Gemini API

// ============================================
// 1. SEMANTIC SEARCH
// ============================================
async function semanticSearchWithAI(query, books, maxResults = 20) {
  if (!window.firebaseServices.isReady) {
    console.warn('Firebase not ready, using basic search');
    return basicSearchFallback(query, books);
  }

  // Build book list for context
  const bookList = books.slice(0, 100).map((b, i) => 
    `${i + 1}. "${b.title}" by ${b.author} - ${(b.description || '').substring(0, 150)}`
  ).join('\n');

  const prompt = `You are a library search assistant for a Philippine school library.
A student is searching for: "${query}"

Available books:
${bookList}

Find the ${maxResults} most relevant books. Consider semantic meaning, not just keywords.
Return JSON array with: index (1-based), relevance (0-100), reason (brief).`;

  try {
    const result = await window.firebaseServices.model.generateContent({
      contents: [{ role: 'user', parts: [{ text: prompt }] }],
      generationConfig: {
        responseMimeType: "application/json",
        responseSchema: {
          type: "array",
          items: {
            type: "object",
            properties: {
              index: { type: "integer" },
              relevance: { type: "number" },
              reason: { type: "string" }
            },
            required: ["index", "relevance", "reason"]
          }
        }
      }
    });

    const matches = JSON.parse(result.response.text());
    
    return matches
      .map(m => ({
        ...books[m.index - 1],
        relevance: m.relevance,
        ai_reason: m.reason,
        search_type: 'semantic'
      }))
      .filter(b => b && b.relevance >= 15)
      .sort((a, b) => b.relevance - a.relevance);

  } catch (error) {
    console.error('Semantic search error:', error);
    return basicSearchFallback(query, books);
  }
}

// Fallback basic search
function basicSearchFallback(query, books) {
  const q = query.toLowerCase();
  const words = q.split(' ').filter(w => w.length > 2);

  return books.map(book => {
    let score = 0;
    const title = (book.title || '').toLowerCase();
    const author = (book.author || '').toLowerCase();
    const desc = (book.description || '').toLowerCase();

    if (title.includes(q)) score += 50;
    if (author.includes(q)) score += 30;
    if (desc.includes(q)) score += 20;
    
    words.forEach(w => {
      if (title.includes(w)) score += 10;
      if (author.includes(w)) score += 5;
      if (desc.includes(w)) score += 3;
    });

    return { ...book, relevance: Math.min(score, 100), search_type: 'basic' };
  })
  .filter(b => b.relevance > 0)
  .sort((a, b) => b.relevance - a.relevance);
}

// ============================================
// 2. BOOK CLASSIFICATION
// ============================================
async function classifyBookWithAI(title, description, author) {
  if (!window.firebaseServices.isReady) {
    return { success: false, suggestion: { category_name: 'General', tags: ['Book'] } };
  }

  const prompt = `Classify this book for a Philippine school library:

Title: ${title}
Author: ${author}
Description: ${description}

Determine:
1. Best category (History, Science, Mathematics, English, Filipino, Technology, Psychology, Business, Design, Fiction, Self-Help)
2. Subject area
3. Grade level (Grade 7-12 or "All Grades")
4. Up to 8 relevant tags

Return JSON with: category_name, subject, grade_level, tags (array), confidence (0-100)`;

  try {
    const result = await window.firebaseServices.model.generateContent({
      contents: [{ role: 'user', parts: [{ text: prompt }] }],
      generationConfig: {
        responseMimeType: "application/json",
        responseSchema: {
          type: "object",
          properties: {
            category_name: { type: "string" },
            subject: { type: "string" },
            grade_level: { type: "string" },
            tags: { type: "array", items: { type: "string" } },
            confidence: { type: "number" }
          },
          required: ["category_name", "subject", "grade_level", "tags", "confidence"]
        }
      }
    });

    const data = JSON.parse(result.response.text());
    return { success: true, suggestion: data, tags: data.tags };

  } catch (error) {
    console.error('Classification error:', error);
    return {
      success: false,
      suggestion: {
        category_name: 'General',
        subject: 'General',
        grade_level: 'All Grades',
        tags: ['Book'],
        confidence: 30
      },
      tags: ['Book']
    };
  }
}

// ============================================
// 3. ZERO-QUERY PREDICTION
// ============================================
async function predictSearchIntent(partialQuery, gradeLevel, subjects, searchHistory) {
  if (!window.firebaseServices.isReady) return [];

  const prompt = `A student at a Philippine school library is typing:
"${partialQuery}"

Context:
- Grade level: ${gradeLevel || 'Unknown'}
- Subjects: ${(subjects || []).join(', ') || 'Unknown'}
- Recent searches: ${(searchHistory || []).slice(0, 5).join(', ') || 'None'}

Predict 5 likely books/topics they want.
Return JSON array with: title, author, category, prediction_score (0-100), reason`;

  try {
    const result = await window.firebaseServices.model.generateContent({
      contents: [{ role: 'user', parts: [{ text: prompt }] }],
      generationConfig: {
        responseMimeType: "application/json",
        responseSchema: {
          type: "array",
          items: {
            type: "object",
            properties: {
              title: { type: "string" },
              author: { type: "string" },
              category: { type: "string" },
              prediction_score: { type: "number" },
              reason: { type: "string" }
            }
          }
        }
      }
    });

    return JSON.parse(result.response.text());

  } catch (error) {
    console.error('Prediction error:', error);
    return [];
  }
}

// ============================================
// 4. SESSION ANALYSIS
// ============================================
async function analyzeSearchSession(queries, clicks, abandoned) {
  if (!window.firebaseServices.isReady) {
    return { frustration_detected: false, frustration_score: 0, reasons: [], suggestions: [] };
  }

  const prompt = `Analyze this library search session for frustration:

Queries: ${JSON.stringify(queries)}
Clicks per search: ${JSON.stringify(clicks)}
Abandoned: ${JSON.stringify(abandoned)}

Determine if student is struggling.
Return JSON with: frustration_detected (boolean), frustration_score (0-100), reasons (array), suggestions (array)`;

  try {
    const result = await window.firebaseServices.model.generateContent({
      contents: [{ role: 'user', parts: [{ text: prompt }] }],
      generationConfig: {
        responseMimeType: "application/json",
        responseSchema: {
          type: "object",
          properties: {
            frustration_detected: { type: "boolean" },
            frustration_score: { type: "number" },
            reasons: { type: "array", items: { type: "string" } },
            suggestions: { type: "array", items: { type: "string" } }
          }
        }
      }
    });

    return JSON.parse(result.response.text());

  } catch (error) {
    console.error('Session analysis error:', error);
    return { frustration_detected: false, frustration_score: 0, reasons: [], suggestions: [] };
  }
}

// Export functions
window.aiFunctions = {
  semanticSearch: semanticSearchWithAI,
  classifyBook: classifyBookWithAI,
  predictSearch: predictSearchIntent,
  analyzeSession: analyzeSearchSession
};

console.log('✅ AI Functions loaded');