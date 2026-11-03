// ai_functions.js - Enhanced with Tagalog/Filipino support + OPAC
// Version 3.1 - Await Firebase readiness, safe fallbacks

(function() {
    'use strict';

    // ===== TAGALOG/FILIPINO KEYWORD DICTIONARY =====
    const TAGALOG_TO_ENGLISH = {
        'kasaysayan': 'history', 'kasaysayan ng': 'history of',
        'agham': 'science', 'agham pampulitika': 'political science',
        'matematika': 'mathematics', 'sipnayan': 'mathematics',
        'panitikan': 'literature', 'literatura': 'literature',
        'tula': 'poetry', 'tulang': 'poem',
        'kuwento': 'story', 'kwento': 'story', 'maikling kuwento': 'short story',
        'nobela': 'novel', 'aklat': 'book', 'libro': 'book',
        'diksiyonaryo': 'dictionary', 'diksyunaryo': 'dictionary',
        'ensayklopidya': 'encyclopedia', 'alamat': 'legend',
        'alamat ng': 'legend of', 'mitolohiya': 'mythology',
        'relihiyon': 'religion', 'pilosopiya': 'philosophy',
        'sining': 'art', 'musika': 'music', 'heograpiya': 'geography',
        'ekonomiya': 'economics', 'pulitika': 'politics',
        'pamahalaan': 'government', 'batas': 'law',
        'medisina': 'medicine', 'kalusugan': 'health',
        'sikolohiya': 'psychology', 'pag-iisip': 'psychology',
        'edukasyon': 'education', 'pag-aaral': 'study',
        'wika': 'language', 'gramatika': 'grammar', 'balarila': 'grammar',
        'teknolohiya': 'technology', 'kompyuter': 'computer',
        'agham pangkompyuter': 'computer science',
        'inhenyeriya': 'engineering', 'negosyo': 'business',
        'pangangalakal': 'business', 'accounting': 'accounting',
        'pagbibilang': 'accounting', 'pagkain': 'food',
        'pagluluto': 'cooking', 'isports': 'sports', 'palakasan': 'sports',
        'basketbol': 'basketball', 'sayaw': 'dance',
        'pelikula': 'film', 'dula': 'drama', 'teatro': 'theater',
        'sanaysay': 'essay', 'talambuhay': 'biography',
        'sariling talambuhay': 'autobiography', 'talaarawan': 'diary',
        'journal': 'journal', 'magasin': 'magazine',
        'pahayagan': 'newspaper', 'balita': 'news',
        'bagong': 'new', 'luma': 'old', 'mahirap': 'difficult',
        'madaling': 'easy', 'advanced': 'advanced',
        'pangunahing': 'basic', 'pangunahin': 'basic',
        'para sa': 'for', 'tungkol sa': 'about', 'tungkol': 'about',
        'may': 'with', 'mayroong': 'with', 'walang': 'without',
        'lahat': 'all', 'pinakamahusay': 'best',
        'pinakamaganda': 'best', 'sikat': 'popular',
        'kilala': 'famous', 'gusto': 'want', 'kailangan': 'need',
        'hanap': 'find', 'hanapin': 'find', 'maghanap': 'search',
        'tingnan': 'view', 'basahin': 'read', 'aralin': 'study',
        'baitang': 'grade', 'grado': 'grade', 'kolehiyo': 'college',
        'hayskul': 'high school', 'elementarya': 'elementary',
        'mag-aaral': 'student', 'estudyante': 'student',
        'guro': 'teacher', 'titser': 'teacher', 'propesor': 'professor',
        'pahina': 'page', 'kabanata': 'chapter',
        'may-akda': 'author', 'manunulat': 'writer',
        'tagasalin': 'translator', 'pamagat': 'title',
        'nilalaman': 'content', 'pabalat': 'cover',
        'edisyon': 'edition', 'taon': 'year',
        'petsa': 'date', 'buwan': 'month',
        'ano': 'what', 'sino': 'who', 'saan': 'where',
        'kailan': 'when', 'bakit': 'why', 'paano': 'how',
        'ilan': 'how many', 'magkano': 'how much',
        'noli me tangere': 'noli me tangere',
        'el filibusterismo': 'el filibusterismo',
        'florante at laura': 'florante at laura',
        'ibong adarna': 'ibong adarna',
        'rizal': 'rizal', 'jose rizal': 'jose rizal',
        'balagtas': 'balagtas', 'francisco balagtas': 'francisco balagtas',
        'lope k. santos': 'lope k. santos',
        'amado v. hernandez': 'amado v. hernandez',
        'nick joaquin': 'nick joaquin',
        'f. sionil jose': 'f. sionil jose',
        'wika at panitikan': 'language and literature',
        'araling panlipunan': 'social studies',
        'edukasyong pangkalusugan': 'health education',
        'edukasyong pisikal': 'physical education',
        'musika at sining': 'music and arts',
        'teknolohiya at kabuhayan': 'technology and livelihood',
        'edukasyon sa pagpapakatao': 'values education',
        'mapeh': 'mapeh', 'filipino': 'filipino',
        'ingles': 'english', 'inggles': 'english',
        'tagalog': 'tagalog', 'cebuano': 'cebuano',
        'ilocano': 'ilocano', 'bisaya': 'bisaya',
        'magbasa': 'read', 'mag-aral': 'study',
        'magsaliksik': 'research', 'pananaliksik': 'research',
        'proyekto': 'project', 'takdang-aralin': 'assignment',
        'homework': 'homework', 'pagsusulit': 'exam',
        'exams': 'exams', 'report': 'report',
        'presentasyon': 'presentation',
        'bago': 'new', 'sariwa': 'fresh',
        'gamit': 'used', 'sira': 'damaged',
        'maayos': 'good condition', 'mabuti': 'good',
        'silid-aklatan': 'library', 'aklatan': 'library',
        'librong': 'book', 'estante': 'shelf', 'rak': 'rack',
        'mesa': 'table',
        'mga aklat': 'books', 'mga libro': 'books',
        'mga kwento': 'stories', 'mga tula': 'poems',
        'mga nobela': 'novels', 'mga diksiyonaryo': 'dictionaries',
        'mga ensayklopidya': 'encyclopedias',
        'mga babasahin': 'reading materials',
        'babasahin': 'reading material',
        'kathang-isip': 'fiction', 'hindi kathang-isip': 'non-fiction',
        'non-fiction': 'non-fiction', 'fiction': 'fiction',
        'totoong kwento': 'true story',
        'kuwentong-bayan': 'folk tale', 'kuwentong bayan': 'folk tale',
        'alamat': 'legend', 'bugtong': 'riddle',
        'salawikain': 'proverb', 'kasabihan': 'saying',
        'epiko': 'epic', 'korido': 'corrido', 'awit': 'song',
        'dula': 'play', 'senakulo': 'senakulo', 'pasyon': 'pasyon',
    };

    // ===== LANGUAGE DETECTION =====
    function detectLanguage(text) {
        if (!text || typeof text !== 'string') return 'unknown';
        const lower = text.toLowerCase();
        const tagalogMarkers = [
            'ang ', ' ng ', ' mga ', ' sa ', ' ay ', ' na ', ' at ',
            ' para sa ', ' tungkol sa ', ' kasaysayan ', ' aklat ',
            ' libro ', ' kuwento ', ' kwento ', ' tula ', ' nobela ',
            ' panitikan ', ' wika ', ' baitang ', ' mag-aaral ',
            ' estudyante ', ' guro ', ' titser ', ' alamat ',
            ' mitolohiya ', ' relihiyon ', ' pilosopiya ',
        ];
        let tagalogScore = 0;
        const totalWords = lower.split(/\s+/).length;
        tagalogMarkers.forEach(m => { if (lower.includes(m)) tagalogScore += 2; });
        lower.split(/\s+/).forEach(word => {
            const clean = word.replace(/[^a-zñ]/g, '');
            if (TAGALOG_TO_ENGLISH[clean]) tagalogScore += 1;
        });
        const score = tagalogScore / Math.max(totalWords, 1);
        if (score > 0.3 || tagalogScore >= 3) return 'tagalog';
        if (tagalogScore >= 1) return 'taglish';
        return 'english';
    }

    // ===== TAGALOG TRANSLATION =====
    function tagalogToEnglish(text) {
        if (!text) return { original: text, translated: text, isTagalog: false };
        let translated = text.toLowerCase();
        const sortedKeys = Object.keys(TAGALOG_TO_ENGLISH).sort((a, b) => b.length - a.length);
        sortedKeys.forEach(tagalog => {
            if (tagalog.includes(' ')) {
                const regex = new RegExp(tagalog.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
                translated = translated.replace(regex, TAGALOG_TO_ENGLISH[tagalog]);
            } else {
                const regex = new RegExp('\\b' + tagalog.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'gi');
                translated = translated.replace(regex, TAGALOG_TO_ENGLISH[tagalog]);
            }
        });
        const lang = detectLanguage(text);
        return {
            original: text, translated,
            isTagalog: lang === 'tagalog' || lang === 'taglish',
            language: lang,
        };
    }

    // ===== BILINGUAL CATALOG SEARCH =====
    function catalogSearchTerms(query) {
        const stop = new Set('ang mga ng na sa si kay kina at o or and the a an of for about tungkol libro aklat book books hanap hanapin naghahanap maghanap gusto ko ako po ba may mayroon meron please find search show me looking want need give'.split(/\s+/));
        const synonyms = {
            programasyon:['programming','program','coding'],
            programa:['program','programming','software'],
            program:['programming','coding'],
            programming:['program','coding','programasyon'],
            coding:['programming','program'],
            kompyuter:['computer','computing'],
            computer:['computing','kompyuter'],
            paghahack:['hacking','hack','cybersecurity'],
            hacking:['hack','hacker','cybersecurity','computer security'],
            hack:['hacking','hacker','cybersecurity'],
            hacker:['hacking','cybersecurity'],
            cybersecurity:['hacking','computer security'],
            kasaysayan:['history','historical'],
            agham:['science'],
            matematika:['mathematics','math'],
            panitikan:['literature'], wika:['language'],
            teknolohiya:['technology'], edukasyon:['education'],
            kalikasan:['nature','environment'],
            talambuhay:['biography'],
            pagluluto:['cooking','cookery'],
            sining:['art','arts'], musika:['music'],
            ekonomiya:['economics','economy'], batas:['law'],
            medisina:['medicine'], hayop:['animal','animals'],
            halaman:['plant','plants'],
            pilipinas:['philippines','philippine'],
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
        const translation = tagalogToEnglish(query);
        const searchQuery = translation.isTagalog ? translation.translated : query;
        const terms = catalogSearchTerms(searchQuery);
        if (!terms.length) return [];
        const norm = v => String(v || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}]+/gu,' ').replace(/\s+/g,' ').trim();
        return books.map(book => {
            const fields = {
                title: norm(book.title), author: norm(book.author), isbn: norm(book.isbn),
                keywords: norm(book.keywords), description: norm(book.description),
                publisher: norm(book.publisher),
                category: norm(book.categories?.name || book.category || ''),
            };
            let score = norm(book.title).includes(norm(searchQuery)) ? 100 : 0;
            const weights = { title:24, keywords:18, category:14, description:10, author:8, publisher:5, isbn:20 };
            let matched = 0;
            terms.forEach(term => {
                let best = 0;
                Object.keys(fields).forEach(key => { if (fields[key].includes(term)) best = Math.max(best, weights[key]); });
                if (best) { score += best + 12; matched++; }
            });
            if (matched === terms.length && matched) score += 30;
            return { ...book, relevance: Math.min(100, score), matched_terms: matched };
        }).filter(b => b.relevance > 0)
          .sort((a, b) => (b.matched_terms - a.matched_terms) || (b.relevance - a.relevance));
    }

    // ===== AWAIT FIREBASE READINESS =====
    async function waitForFirebase(timeoutMs = 8000) {
        if (!window.firebaseServices) return false;
        if (window.firebaseServices.isReady) return true;
        try {
            await Promise.race([
                window.firebaseServices.initialize(),
                new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), timeoutMs)),
            ]);
            return !!window.firebaseServices.isReady;
        } catch (err) {
            console.warn('waitForFirebase:', err.message);
            return false;
        }
    }

    // ===== AI FUNCTIONS OBJECT =====
    const aiFunctions = {
        waitForFirebase,

        semanticSearch: async function(query, books, limit = 20) {
            const localResults = basicSearchFallback(query, books).slice(0, limit);
            if (!localResults.length) return [];

            const ready = await waitForFirebase();
            if (!ready || !window.firebaseServices.semanticSearch) return localResults;

            try {
                const translation = tagalogToEnglish(query);
                const aiQuery = translation.isTagalog ? translation.translated : query;
                const aiResults = await window.firebaseServices.semanticSearch(aiQuery, books, limit);
                if (!Array.isArray(aiResults) || !aiResults.length) return localResults;
                const byId = new Map(localResults.map((b, i) => [String(b.id), { ...b, _localRank: i }]));
                aiResults.forEach(b => {
                    const key = String(b.id);
                    if (byId.has(key)) byId.set(key, { ...byId.get(key), _aiRank: 1 });
                });
                return [...byId.values()].sort((a, b) =>
                    ((b._aiRank || 0) - (a._aiRank || 0)) ||
                    ((b.matched_terms || 0) - (a.matched_terms || 0)) ||
                    ((b.relevance || 0) - (a.relevance || 0))
                ).slice(0, limit);
            } catch (err) {
                console.warn('AI semantic ranking unavailable; using bilingual catalog search:', err);
                return localResults;
            }
        },

        predictSearch: async function(query, gradeLevel, subjects, history) {
            if (!query || query.length < 2) return [];
            const translation = tagalogToEnglish(query);
            const searchQuery = translation.isTagalog ? translation.translated : query;
            const ready = await waitForFirebase();
            if (!ready || !window.firebaseServices.predictSearch) return [];
            try {
                return await window.firebaseServices.predictSearch(searchQuery, gradeLevel, subjects, history);
            } catch (err) {
                console.warn('AI predictive search failed:', err);
                return [];
            }
        },

        analyzeSession: async function(queries, clicks, abandoned) {
            const ready = await waitForFirebase();
            if (ready && window.firebaseServices.analyzeSession) {
                try { return await window.firebaseServices.analyzeSession(queries, clicks, abandoned); }
                catch (err) { console.warn('AI session analysis failed:', err); }
            }
            const totalClicks = clicks.reduce((a, b) => a + b, 0);
            const frustration = queries.length >= 3 && totalClicks === 0;
            return {
                frustration_detected: frustration,
                frustration_score: frustration ? 70 : 0,
                reasons: frustration ? ['Multiple searches without clicks', 'No results selected'] : [],
                suggestions: frustration ? ['Try simpler keywords', 'Ask librarian for help'] : [],
            };
        },

        classifyBook: async function(title, description, categories) {
            const ready = await waitForFirebase();
            if (ready && window.firebaseServices.classifyBook) {
                try { return await window.firebaseServices.classifyBook(title, description, categories); }
                catch (err) { console.warn('AI classification failed:', err); }
            }
            return null;
        },

        detectLanguage, tagalogToEnglish,
        basicSearch: basicSearchFallback,

        opacSearch: async function(filters, books) {
            let results = [...books];
            if (filters.title) { const t = filters.title.toLowerCase(); results = results.filter(b => (b.title || '').toLowerCase().includes(t)); }
            if (filters.author) { const a = filters.author.toLowerCase(); results = results.filter(b => (b.author || '').toLowerCase().includes(a)); }
            if (filters.isbn) { const i = filters.isbn.toLowerCase(); results = results.filter(b => (b.isbn || '').toLowerCase().includes(i)); }
            if (filters.publisher) { const p = filters.publisher.toLowerCase(); results = results.filter(b => (b.publisher || '').toLowerCase().includes(p)); }
            if (filters.category) {
                results = results.filter(b => {
                    const cat = (b.categories && b.categories.name) || b.category || '';
                    return cat.toLowerCase() === filters.category.toLowerCase();
                });
            }
            if (filters.year_from) results = results.filter(b => parseInt(b.year_published || 0) >= parseInt(filters.year_from));
            if (filters.year_to) results = results.filter(b => parseInt(b.year_published || 0) <= parseInt(filters.year_to));
            if (filters.available_only) results = results.filter(b => (b.available || 0) > 0);
            if (filters.q) {
                const translation = tagalogToEnglish(filters.q);
                const q = (translation.isTagalog ? translation.translated : filters.q).toLowerCase();
                results = results.map(b => {
                    let score = 0;
                    const title = (b.title || '').toLowerCase();
                    const author = (b.author || '').toLowerCase();
                    const desc = (b.description || '').toLowerCase();
                    const keywords = (b.keywords || '').toLowerCase();
                    if (title.includes(q)) score += 50;
                    if (author.includes(q)) score += 30;
                    if (desc.includes(q)) score += 20;
                    if (keywords.includes(q)) score += 15;
                    return { ...b, relevance: score };
                }).filter(b => b.relevance > 0).sort((a, b) => b.relevance - a.relevance);
            }
            return results;
        }
    };

    window.aiFunctions = aiFunctions;
    console.log('✅ AI Functions v3.1 loaded (Tagalog support enabled)');
})();