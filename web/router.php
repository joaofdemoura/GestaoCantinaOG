<?php
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(str_starts_with($path,'/api/')){require __DIR__.'/api/index.php';return true;}
if($path==='/'){header('Location: /dist/');return true;}
if(str_starts_with($path,'/dist/')&&!str_contains($path,'..')&&!str_ends_with(strtolower($path),'.php'))return false;
http_response_code(404);echo 'Não encontrado';return true;