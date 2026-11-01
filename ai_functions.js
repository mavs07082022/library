// ai_functions.js - Enhanced with Tagalog/Filipino support + OPAC
// Version 3.0 - Adds multilingual search, language detection, translation hints

(function() {
    'use strict';

    // ===== TAGALOG/FILIPINO KEYWORD DICTIONARY =====
    // Maps common Tagalog search terms to English library terms
    const TAGALOG_TO_ENGLISH = {
        // Subjects/Topics
        'kasaysayan': 'history',
        'kasaysayan ng': 'history of',
        'agham': 'science',
        'agham pampulitika': 'political science',
        'matematika': 'mathematics',
        'sipnayan': 'mathematics',
        'panitikan': 'literature',
        'literatura': 'literature',
        'tula': 'poetry',
        'tulang': 'poem',
        'kuwento': 'story',
        'kwento': 'story',
        'maikling kuwento': 'short story',
        'nobela': 'novel',
        'aklat': 'book',
        'libro': 'book',
        'diksiyonaryo': 'dictionary',
        'diksyunaryo': 'dictionary',
        'ensayklopidya': 'encyclopedia',
        'alamat': 'legend',
        'alamat ng': 'legend of',
        'mitolohiya': 'mythology',
        'relihiyon': 'religion',
        'pilosopiya': 'philosophy',
        'sining': 'art',
        'musika': 'music',
        'heograpiya': 'geography',
        'ekonomiya': 'economics',
        'pulitika': 'politics',
        'pamahalaan': 'government',
        'batas': 'law',
        'medisina': 'medicine',
        'kalusugan': 'health',
        'sikolohiya': 'psychology',
        'pag-iisip': 'psychology',
        'edukasyon': 'education',
        'pag-aaral': 'study',
        'wika': 'language',
        'gramatika': 'grammar',
        'balarila': 'grammar',
        'teknolohiya': 'technology',
        'kompyuter': 'computer',
        'agham pangkompyuter': 'computer science',
        'inhenyeriya': 'engineering',
        'negosyo': 'business',
        'pangangalakal': 'business',
        'accounting': 'accounting',
        'pagbibilang': 'accounting',
        'pagkain': 'food',
        'pagluluto': 'cooking',
        'isports': 'sports',
        'palakasan': 'sports',
        'basketbol': 'basketball',
        'musika': 'music',
        'sayaw': 'dance',
        'pelikula': 'film',
        'dula': 'drama',
        'teatro': 'theater',
        'tula': 'poetry',
        'sanaysay': 'essay',
        'talambuhay': 'biography',
        'sariling talambuhay': 'autobiography',
        'talaarawan': 'diary',
        'journal': 'journal',
        'magasin': 'magazine',
        'pahayagan': 'newspaper',
        'balita': 'news',

        // Adjectives / intents
        'bagong': 'new',
        'luma': 'old',
        'mahirap': 'difficult',
        'madaling': 'easy',
        'advanced': 'advanced',
        'pangunahing': 'basic',
        'pangunahin': 'basic',
        'intermediate': 'intermediate',
        'para sa': 'for',
        'tungkol sa': 'about',
        'tungkol': 'about',
        'may': 'with',
        'mayroong': 'with',
        'walang': 'without',
        'lahat': 'all',
        'pinakamahusay': 'best',
        'pinakamaganda': 'best',
        'sikat': 'popular',
        'kilala': 'famous',
        'gusto': 'want',
        'kailangan': 'need',
        'hanap': 'find',
        'hanapin': 'find',
        'maghanap': 'search',
        'tingnan': 'view',
        'basahin': 'read',
        'aralin': 'study',

        // Level / grade
        'baitang': 'grade',
        'grado': 'grade',
        'kolehiyo': 'college',
        'hayskul': 'high school',
        'elementarya': 'elementary',
        'mag-aaral': 'student',
        'estudyante': 'student',
        'guro': 'teacher',
        'titser': 'teacher',
        'propesor': 'professor',

        // Common book terms
        'aklat': 'book',
        'libro': 'book',
        'pahina': 'page',
        'kabanata': 'chapter',
        'may-akda': 'author',
        'manunulat': 'writer',
        'tagasalin': 'translator',
        'pamagat': 'title',
        'nilalaman': 'content',
        'pabalat': 'cover',
        'edisyon': 'edition',
        'taon': 'year',
        'petsa': 'date',
        'buwan': 'month',

        // Question words
        'ano': 'what',
        'sino': 'who',
        'saan': 'where',
        'kailan': 'when',
        'bakit': 'why',
        'paano': 'how',
        'ilan': 'how many',
        'magkano': 'how much',

        // Filipino-specific literature
        'noli me tangere': 'noli me tangere',
        'el filibusterismo': 'el filibusterismo',
        'florante at laura': 'florante at laura',
        'ibong adarna': 'ibong adarna',
        'rizal': 'rizal',
        'jose rizal': 'jose rizal',
        'balagtas': 'balagtas',
        'francisco balagtas': 'francisco balagtas',
        'lope k. santos': 'lope k. santos',
        'amado v. hernandez': 'amado v. hernandez',
        'nick joaquin': 'nick joaquin',
        'f. sionil jose': 'f. sionil jose',

        // More subjects
        'wika at panitikan': 'language and literature',
        'araling panlipunan': 'social studies',
        'edukasyong pangkalusugan': 'health education',
        'edukasyong pisikal': 'physical education',
        'musika at sining': 'music and arts',
        'teknolohiya at kabuhayan': 'technology and livelihood',
        'edukasyon sa pagpapakatao': 'values education',
        'mapeh': 'mapeh',
        'filipino': 'filipino',
        'ingles': 'english',
        'inggles': 'english',
        'tagalog': 'tagalog',
        'cebuano': 'cebuano',
        'ilocano': 'ilocano',
        'bisaya': 'bisaya',

        // Verbs / intents
        'magbasa': 'read',
        'mag-aral': 'study',
        'magsaliksik': 'research',
        'magsaliksik': 'research',
        'pananaliksik': 'research',
        'proyekto': 'project',
        'takdang-aralin': 'assignment',
        'homework': 'homework',
        'pagsusulit': 'exam',
        'exams': 'exams',
        'report': 'report',
        'presentasyon': 'presentation',

        // Feelings / states
        'bago': 'new',
        'sariwa': 'fresh',
        'luma': 'old',
        'gamit': 'used',
        'sira': 'damaged',
        'maayos': 'good condition',
        'mabuti': 'good',

        // Locations in library
        'silid-aklatan': 'library',
        'aklatan': 'library',
        'librong': 'book',
        'estante': 'shelf',
        'rak': 'rack',
        'mesa': 'table',

        // Common phrases
        'mga aklat': 'books',
        'mga libro': 'books',
        'mga kwento': 'stories',
        'mga tula': 'poems',
        'mga nobela': 'novels',
        'mga diksiyonaryo': 'dictionaries',
        'mga ensayklopidya': 'encyclopedias',
        'mga babasahin': 'reading materials',
        'babasahin': 'reading material',
        'kathang-isip': 'fiction',
        'hindi kathang-isip': 'non-fiction',
        'non-fiction': 'non-fiction',
        'fiction': 'fiction',
        'totoong kwento': 'true story',
        'kuwentong-bayan': 'folk tale',
        'kuwentong bayan': 'folk tale',
        'alamat': 'legend',
        'bugtong': 'riddle',
        'salawikain': 'proverb',
        'kasabihan': 'saying',
        'epiko': 'epic',
        'korido': 'corrido',
        'awit': 'song',
        'dula': 'play',
        'senakulo': 'senakulo',
        'pasyon': 'pasyon',
    };

    // ===== LANGUAGE DETECTION =====
    function detectLanguage(text) {
        if (!text || typeof text !== 'string') return 'unknown';

        const lower = text.toLowerCase();
        
        // Strong Tagalog indicators
        const tagalogMarkers = [
            'ang ', ' ng ', ' mga ', ' sa ', ' ay ', ' na ', ' at ',
            ' para sa ', ' tungkol sa ', ' kasaysayan ', ' aklat ',
            ' libro ', ' kuwento ', ' kwento ', ' tula ', ' nobela ',
            ' panitikan ', ' wika ', ' baitang ', ' mag-aaral ',
            ' estudyante ', ' guro ', ' titser ', ' alamat ',
            ' mitolohiya ', ' relihiyon ', ' pilosopiya ',
        ];

        let tagalogScore = 0;
        let totalWords = lower.split(/\s+/).length;

        tagalogMarkers.forEach(marker => {
            if (lower.includes(marker)) tagalogScore += 2;
        });

        // Check individual words
        const words = lower.split(/\s+/);
        words.forEach(word => {
            const cleanWord = word.replace(/[^a-zñ]/g, '');
            if (TAGALOG_TO_ENGLISH[cleanWord]) tagalogScore += 1;
        });

        // Normalize
        const score = tagalogScore / Math.max(totalWords, 1);

        if (score > 0.3 || tagalogScore >= 3) {
            return 'tagalog';
        } else if (tagalogScore >= 1) {
            return 'taglish'; // Mixed
        }
        return 'english';
    }

    // ===== TAGALOG TRANSLATION =====
    function tagalogToEnglish(text) {
        if (!text) return { original: text, translated: text, isTagalog: false };

        const lower = text.toLowerCase();
        let translated = lower;

        // Sort keys by length descending to match longer phrases first
        const sortedKeys = Object.keys(TAGALOG_TO_ENGLISH).sort((a, b) => b.length - a.length);

        sortedKeys.forEach(tagalog => {
            // Use word boundary matching for shorter terms, substring for phrases
            if (tagalog.includes(' ')) {
                // Phrase - replace globally
                const regex = new RegExp(tagalog.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
                translated = translated.replace(regex, TAGALOG_TO_ENGLISH[tagalog]);
            } else {
                // Single word - word boundary
                const regex = new RegExp('\\b' + tagalog.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'gi');
                translated = translated.replace(regex, TAGALOG_TO_ENGLISH[tagalog]);
            }
        });

        const lang = detectLanguage(text);

        return {
            original: text,
            translated: translated,
            isTagalog: lang === 'tagalog' || lang === 'taglish',
            language: lang
        };
    }

    // ===== BILINGUAL CATALOG SEARCH (English / Filipino / Taglish) =====
    function catalogSearchTerms(query) {
        const stop = new Set('ang mga ng na sa si kay kina at o or and the a an of for about tungkol libro aklat book books hanap hanapin naghahanap maghanap gusto ko ako po ba may mayroon meron please find search show me looking want need give'.split(/\s+/));
        const synonyms = {
            programasyon:['programming','program','coding'], programa:['program','programming','software'],
            program:['programming','coding'], programming:['program','coding','programasyon'], coding:['programming','program'],
            kompyuter:['computer','computing'], computer:['computing','kompyuter'],
            paghahack:['hacking','hack','cybersecurity'], hacking:['hack','hacker','cybersecurity','computer security'],
            hack:['hacking','hacker','cybersecurity'], hacker:['hacking','cybersecurity'], cybersecurity:['hacking','computer security'],
            kasaysayan:['history','historical'], agham:['science'], matematika:['mathematics','math'],
            panitikan:['literature'], wika:['language'], teknolohiya:['technology'], edukasyon:['education'],
            kalikasan:['nature','environment'], talambuhay:['biography'], pagluluto:['cooking','cookery'],
            sining:['art','arts'], musika:['music'], ekonomiya:['economics','economy'], batas:['law'],
            medisina:['medicine'], hayop:['animal','animals'], halaman:['plant','plants'], pilipinas:['philippines','philippine']
        };
        const raw = String(query || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}]+/gu,' ').trim().split(/\s+/).filter(Boolean);
        const out = new Set();
        raw.forEach(word => {
            if (stop.has(word) || word.length < 2) return;
            out.add(word);
            (synonyms[word] || []).forEach(term => term.split(/\s+/).forEach(t => out.add(t)));
            if (word.startsWith('pag') && word.length > 6) out.add(word.slice(3));
            if (word.endsWith('s') && word.length > 4) out.add(word.slice(0,-1));
        });
        return [...out];
    }

    function basicSearchFallback(query, books) {
        if (!query || !Array.isArray(books)) return [];
        const terms = catalogSearchTerms(query);
        if (!terms.length) return [];
        const norm = value => String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}]+/gu,' ').replace(/\s+/g,' ').trim();
        return books.map(book => {
            const fields = {
                title:norm(book.title), author:norm(book.author), isbn:norm(book.isbn),
                keywords:norm(book.keywords), description:norm(book.description), publisher:norm(book.publisher),
                category:norm(book.categories?.name || book.category || '')
            };
            let score = norm(book.title).includes(norm(query)) ? 100 : 0;
            const weights = {title:24,keywords:18,category:14,description:10,author:8,publisher:5,isbn:20};
            let matched = 0;
            terms.forEach(term => {
                let best = 0;
                Object.keys(fields).forEach(key => { if (fields[key].includes(term)) best = Math.max(best, weights[key]); });
                if (best) { score += best + 12; matched++; }
            });
            if (matched === terms.length && matched) score += 30;
            return {...book, relevance:Math.min(100,score), matched_terms:matched};
        }).filter(book => book.relevance > 0)
          .sort((a,b) => (b.matched_terms-a.matched_terms) || (b.relevance-a.relevance));
    }

    // ===== AI FUNCTIONS OBJECT =====
    const aiFunctions = {
        /**
         * Semantic search with Tagalog support
         */
        semanticSearch: async function(query, books, limit = 20) {
            // Search the supplied in-system catalog first. This prevents online/generated books
            // from appearing and makes Tagalog/Taglish intent deterministic even without AI.
            const localResults = basicSearchFallback(query, books).slice(0, limit);
            if (!localResults.length) return [];
            if (window.firebaseServices && window.firebaseServices.isReady && window.firebaseServices.semanticSearch) {
                try {
                    const aiResults = await window.firebaseServices.semanticSearch(query, books, limit);
                    const byId = new Map(localResults.map((b, i) => [String(b.id), {...b, _localRank:i}]));
                    (Array.isArray(aiResults) ? aiResults : []).forEach(b => {
                        const key = String(b.id);
                        if (byId.has(key)) byId.set(key, {...byId.get(key), _aiRank:1});
                    });
                    return [...byId.values()].sort((a,b) => ((b.matched_terms||0)-(a.matched_terms||0)) || ((b._aiRank||0)-(a._aiRank||0)) || ((b.relevance||0)-(a.relevance||0))).slice(0,limit);
                } catch (err) { console.warn('AI semantic ranking unavailable; using bilingual catalog search:', err); }
            }
            return localResults;
        },

        /**
         * Predictive search with Tagalog support
         */
        predictSearch: async function(query, gradeLevel, subjects, history) {
            if (!query || query.length < 2) return [];

            const translation = tagalogToEnglish(query);
            const searchQuery = translation.isTagalog ? translation.translated : query;

            if (window.firebaseServices && window.firebaseServices.isReady && window.firebaseServices.predictSearch) {
                try {
                    return await window.firebaseServices.predictSearch(searchQuery, gradeLevel, subjects, history);
                } catch (err) {
                    console.warn('AI predictive search failed:', err);
                }
            }

            // Local fallback prediction
            return [];
        },

        /**
         * Analyze user search session for frustration
         */
        analyzeSession: async function(queries, clicks, abandoned) {
            if (window.firebaseServices && window.firebaseServices.isReady && window.firebaseServices.analyzeSession) {
                try {
                    return await window.firebaseServices.analyzeSession(queries, clicks, abandoned);
                } catch (err) {
                    console.warn('AI session analysis failed:', err);
                }
            }

            // Heuristic fallback
            const totalClicks = clicks.reduce((a, b) => a + b, 0);
            const frustration = queries.length >= 3 && totalClicks === 0;

            return {
                frustration_detected: frustration,
                frustration_score: frustration ? 70 : 0,
                reasons: frustration ? ['Multiple searches without clicks', 'No results selected'] : [],
                suggestions: frustration ? ['Try simpler keywords', 'Ask librarian for help'] : []
            };
        },

        /**
         * Classify book into category (for AI classification)
         */
        classifyBook: async function(title, description, categories) {
            if (window.firebaseServices && window.firebaseServices.isReady && window.firebaseServices.classifyBook) {
                try {
                    return await window.firebaseServices.classifyBook(title, description, categories);
                } catch (err) {
                    console.warn('AI classification failed:', err);
                }
            }
            return null;
        },

        // ===== NEW: Public utility methods =====
        detectLanguage: detectLanguage,
        tagalogToEnglish: tagalogToEnglish,
        basicSearch: basicSearchFallback,

        /**
         * OPAC search - combined filter + relevance scoring
         * Used by opac.php for advanced search
         */
        opacSearch: async function(filters, books) {
            let results = [...books];

            // Apply field filters
            if (filters.title) {
                const t = filters.title.toLowerCase();
                results = results.filter(b => (b.title || '').toLowerCase().includes(t));
            }
            if (filters.author) {
                const a = filters.author.toLowerCase();
                results = results.filter(b => (b.author || '').toLowerCase().includes(a));
            }
            if (filters.isbn) {
                const i = filters.isbn.toLowerCase();
                results = results.filter(b => (b.isbn || '').toLowerCase().includes(i));
            }
            if (filters.publisher) {
                const p = filters.publisher.toLowerCase();
                results = results.filter(b => (b.publisher || '').toLowerCase().includes(p));
            }
            if (filters.category) {
                results = results.filter(b => {
                    const cat = (b.categories && b.categories.name) || b.category || '';
                    return cat.toLowerCase() === filters.category.toLowerCase();
                });
            }
            if (filters.year_from) {
                results = results.filter(b => parseInt(b.year_published || 0) >= parseInt(filters.year_from));
            }
            if (filters.year_to) {
                results = results.filter(b => parseInt(b.year_published || 0) <= parseInt(filters.year_to));
            }
            if (filters.available_only) {
                results = results.filter(b => (b.available || 0) > 0);
            }

            // If free-text query, also rank by relevance
            if (filters.q) {
                const q = filters.q.toLowerCase();
                const translation = tagalogToEnglish(q);
                const searchQ = translation.isTagalog ? translation.translated : q;
                
                results = results.map(b => {
                    let score = 0;
                    const title = (b.title || '').toLowerCase();
                    const author = (b.author || '').toLowerCase();
                    const desc = (b.description || '').toLowerCase();
                    const keywords = (b.keywords || '').toLowerCase();
                    
                    if (title.includes(searchQ)) score += 50;
                    if (author.includes(searchQ)) score += 30;
                    if (desc.includes(searchQ)) score += 20;
                    if (keywords.includes(searchQ)) score += 15;
                    
                    return { ...b, relevance: score };
                })
                .filter(b => b.relevance > 0)
                .sort((a, b) => b.relevance - a.relevance);
            }

            return results;
        }
    };

    // Expose to global
    window.aiFunctions = aiFunctions;

    console.log('✅ AI Functions v3.0 loaded (Tagalog support enabled)');
})();