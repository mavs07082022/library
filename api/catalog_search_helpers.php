<?php
/** Shared deterministic English/Filipino/Taglish catalog query processor. */
function catalog_norm($s) {
    $s = function_exists('mb_strtolower') ? mb_strtolower((string)$s, 'UTF-8') : strtolower((string)$s);
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
function catalog_terms($query) {
    $q = catalog_norm($query);
    // Conversational words that describe the request, not the book topic.
    $stop = preg_split('/\s+/u', 'ang mga ng na sa si kay kina at o or and the a an of for about tungkol libro aklat book books hanap hanapin naghahanap maghanap gusto ko ako po ba may mayroon meron please find search show me looking want need give');
    $stop = array_fill_keys($stop, true);
    $syn = [
        'programasyon'=>['programming','program','coding','software development'],
        'programa'=>['program','programming','software'], 'program'=>['programming','coding'],
        'programming'=>['program','coding','programasyon'], 'programminga'=>['programming'],
        'pagcode'=>['coding','programming'], 'coding'=>['programming','program'],
        'kompyuter'=>['computer','computing'], 'computer'=>['computing','kompyuter'],
        'paghahack'=>['hacking','hack','cybersecurity'], 'pagha hack'=>['hacking','hack','cybersecurity'],
        'hacking'=>['hack','hacker','cybersecurity','computer security'], 'hack'=>['hacking','hacker','cybersecurity'],
        'hacker'=>['hacking','cybersecurity'], 'cybersecurity'=>['hacking','computer security','information security'],
        'seguridad'=>['security','cybersecurity'], ' seguridad'=>['security'],
        ' agham'=>['science'], 'agham'=>['science'], 'kasaysayan'=>['history','historical'],
        'matematika'=>['mathematics','math'], 'panitikan'=>['literature'], 'wika'=>['language'],
        'teknolohiya'=>['technology'], 'edukasyon'=>['education'], 'kalikasan'=>['nature','environment'],
        'talambuhay'=>['biography'], 'pagluluto'=>['cooking','cookery'], 'sining'=>['art','arts'],
        'musika'=>['music'], 'ekonomiya'=>['economics','economy'], 'batas'=>['law'],
        'medisina'=>['medicine'], 'hayop'=>['animal','animals'], 'halaman'=>['plant','plants'],
        'pilipinas'=>['philippines','philippine'], 'paaralan'=>['school'], 'guro'=>['teacher']
    ];
    $raw = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    $terms=[];
    foreach ($raw as $word) {
        if (isset($stop[$word]) || (function_exists('mb_strlen') ? mb_strlen($word,'UTF-8') : strlen($word)) < 2) continue;
        $terms[$word]=true;
        if (isset($syn[$word])) foreach ($syn[$word] as $alt) {
            foreach (preg_split('/\s+/u', catalog_norm($alt), -1, PREG_SPLIT_NO_EMPTY) as $a) $terms[$a]=true;
        }
        // Common Filipino affixes and English inflections; keep the original too.
        if (preg_match('/^pag(.+)$/u',$word,$m) && strlen($m[1])>=3) $terms[$m[1]]=true;
        if (preg_match('/(.{4,})s$/u',$word,$m)) $terms[$m[1]]=true;
    }
    return array_keys($terms);
}
function catalog_score_book($book, $query, $category='') {
    $terms = catalog_terms($query);
    if (!$terms) return $query === '' ? 1 : 0;
    $fields = [
        'title'=>catalog_norm($book['title'] ?? ''),
        'author'=>catalog_norm($book['author'] ?? ''),
        'isbn'=>catalog_norm($book['isbn'] ?? ''),
        'keywords'=>catalog_norm($book['keywords'] ?? ''),
        'description'=>catalog_norm($book['description'] ?? ''),
        'publisher'=>catalog_norm($book['publisher'] ?? ''),
        'category'=>catalog_norm($category ?: ($book['category'] ?? ($book['categories']['name'] ?? '')))
    ];
    $full = catalog_norm($query); $score=0; $matched=[];
    if ($full !== '' && strpos($fields['title'],$full)!==false) $score += 100;
    $weights=['title'=>24,'keywords'=>18,'category'=>14,'description'=>10,'author'=>8,'publisher'=>5,'isbn'=>20];
    foreach ($terms as $term) {
        $best=0;
        foreach ($fields as $field=>$value) {
            if ($value !== '' && strpos($value,$term)!==false) $best=max($best,$weights[$field]);
        }
        if ($best>0) { $matched[$term]=true; $score += $best; }
    }
    // OR matching is intentional: a natural request such as "libro tungkol sa programming at hacking"
    // should surface catalog records about either programming or hacking, even if no single title has both.
    if (!$matched) return 0;
    // Prefer books matching more of the user's meaningful topic terms.
    $score += count($matched)*12;
    if (count($matched)===count($terms)) $score += 30;
    return min(999,$score);
}
?>