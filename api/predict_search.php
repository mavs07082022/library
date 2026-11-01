<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/catalog_search_helpers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'predictions'=>[],'message'=>'Method not allowed'],405);
$input=getInput(); $query=trim((string)($input['partial_query']??''));
$len=function_exists('mb_strlen')?mb_strlen($query,'UTF-8'):strlen($query);
if($len<2) jsonResponse(['success'=>true,'predictions'=>[],'count'=>0,'source'=>'supabase_books']);
if($len>150) $query=function_exists('mb_substr')?mb_substr($query,0,150,'UTF-8'):substr($query,0,150);
function ps_int($v){return is_numeric($v)?max(0,(int)$v):0;}
try {
    $books=supabaseRequest('books?select=id,title,author,isbn,keywords,description,publisher,quantity,available,category_id,cover_image,year_published');
    if(!is_array($books)) $books=[];
    $cats=supabaseRequest('categories?select=id,name'); $catMap=[];
    if(is_array($cats)) foreach($cats as $c) if(isset($c['id'])) $catMap[(string)$c['id']]=(string)($c['name']??'');
    $terms=catalog_terms($query); $rows=[];
    foreach($books as $book){
        if(!is_array($book)||empty($book['id'])||empty($book['title'])) continue;
        $cat=$catMap[(string)($book['category_id']??'')]??'';
        $score=catalog_score_book($book,$query,$cat);
        if($score<=0) continue;
        $available=ps_int($book['available']??0); $quantity=ps_int($book['quantity']??0);
        $rows[]=[
            'id'=>(string)$book['id'],'title'=>(string)$book['title'],'author'=>(string)($book['author']??'Unknown author'),
            'isbn'=>(string)($book['isbn']??''),'category'=>$cat?:'Uncategorized','quantity'=>$quantity,
            'available'=>$available,'available_count'=>$available,
            'availability_label'=>$available>0?($available.' available'):'Unavailable',
            'prediction_score'=>$score,'matched_terms'=>$terms,'source'=>'stored_book'
        ];
    }
    usort($rows,function($a,$b){return ($b['prediction_score']<=>$a['prediction_score'])?:strcasecmp($a['title'],$b['title']);});
    $rows=array_slice($rows,0,10);
    jsonResponse(['success'=>true,'predictions'=>$rows,'count'=>count($rows),'source'=>'supabase_books','query_terms'=>$terms,'matching'=>'multilingual_catalog_keyword']);
} catch(Throwable $e){error_log('Predictive search failed: '.$e->getMessage());jsonResponse(['success'=>false,'predictions'=>[],'message'=>'Unable to load catalog suggestions.'],500);}
?>