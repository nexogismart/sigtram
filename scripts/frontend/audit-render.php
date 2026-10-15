<?php
// Local, read-only render audit. Does not create sessions, users, documents or permissions in the DB.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
config(['cache.default'=>'array','session.driver'=>'array','mail.default'=>'array']);
$schema=rtrim(config('auth.sigtram_sistema_schema','sistema'),'.').'.';
$user=App\Models\User::where('de_lgin','rfloresm')->where('in_esta','1')->firstOrFail();
$core=$app->make(App\Contracts\Services\SecurityCoreInterface::class);
$cargos=$core->obtenerCargosUsuario((int)$user->cdgo_lgin);
$cargo=$cargos->first(fn($c)=>stripos($c->de_area??'','INFORMACI')!==false)??$cargos->first();
$paths=[
'/mod_a/sgl/index','/mod_a/sgl/pago_proveedores/index','/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago','/mod_a/sgl/pago_proveedores/ver_requerimiento_logistica','/mod_a/sgl/pago_proveedores/ver_requerimiento_locador','/mod_a/sgl/pago_proveedores/ver_requerimiento_finanzas','/mod_a/sgl/pago_proveedores/ver_requerimiento_presupuesto','/mod_a/sgl/pago_proveedores/ver_requerimiento_tesoreria','/mod_a/sgl/pago_proveedores/v2_lista_reporte_requerimientos_general',
'/mod_gene/mod_tram_inter/buscar-tramite','/mod_gene/mod_tram_inter/correo-interno','/mod_gene/mod_tram_inter/ingresar-tramite','/mod_gene/mod_tram_inter/reporte-cargo-interno','/mod_gene/mod_tram_inter/reporte-tramite','/mod_gene/mod_tram_inter/v2_lista_reporte_area_cargo','/mod_gene/mod_tram_inter/ver_secuencial_area',
'/mod_gm/mod_tram_exte/index','/mod_gm/mod_tram_exte/segi_expedi','/mod_gm/mod_tram_exte/segi_archivo','/mod_gm/mod_tram_exte/lista_expedi_todo','/mod_gm/mod_tram_exte/lista_repor_area','/mod_gm/mod_tram_exte/lista_reporte','/mod_gm/mod_tram_exte/notificar_pltfrma','/mod_gm/mod_tram_exte/redirigir','/mod_gm/mod_tram_exte/reporte-expediente','/mod_gm/mod_tram_exte/segi_adjuntar','/mod_gm/mod_tram_exte/segi_verexpedi','/mod_gm/mod_tram_exte/v2_lista_reporte_area_cargo',
'/mod_seguridad/seguridad-usuario','/mod_seguridad/seguridad-perfiles','/mod_seguridad/seguridad-perfiles-opciones','/mod_seguridad/seguridad-opciones','/mod_seguridad/seguridad-cargos','/mod_seguridad/seguridad-accesos','/mod_seguridad/cambiar-contraseña',
'/mod_ti/helpdesk/index','/mod_ti/helpdesk/bandeja','/mod_ti/helpdesk/consulta','/mod_ti/helpdesk/pantalla','/mod_ti/dispositivo_seg/index','/mod_ti/dispositivo_seg/mapa','/mod_ti/dispositivo_seg/graficos','/mod_ti/firma_email/index','/mod_ti/reporte_publicidad/index','/mod_ti/reporte_publicidad_2026/index',
'/mod_gdu/band_consulta_gdu','/mod_gp/omaped/index','/mod_oac/acceso_informacion/acceso_info','/mod_promesa/index','/mod_reportes/reporte_tramites'];
$menu=App\Support\WorkspaceNavigation::decorate($core->obtenerMenuUsuario((int)$user->cdgo_lgin,(int)$cargo->cdgo_crgo));
$skipped=[];
foreach($menu as $group){
 if(!($group['workspace']['active']??true))continue;
 foreach($group['children']?:[$group] as $entry){
  $url=(string)($entry['url']??'');$path=parse_url($url,PHP_URL_PATH);
  if(!$path||$path==='#'||str_starts_with($url,'http')||preg_match('/(?:eliminar|delete|borrar|guardar|finalizar|generar|enviar|descargar|logout|ping)/i',$path)){$skipped[]=['id'=>$entry['id']??null,'label'=>$entry['label'],'reason'=>'externo, acción o destino no navegable'];continue;}
  $paths[]=$path;
 }
}
$paths=array_values(array_unique($paths));
$rows=[];
foreach($paths as $path){
 DB::beginTransaction(); DB::statement('SET TRANSACTION READ ONLY');
 try {
  Auth::login($user);$session=$app['session']->driver();$session->start();$session->put('sigtram_cargo_id',(int)$cargo->cdgo_crgo);$session->put('sigtram_area_nombre',$cargo->de_area);$session->put('sigtram_cargo_nombre',$cargo->de_crgo);
  $request=Request::create($path,'GET');$request->setLaravelSession($session);$request->setUserResolver(fn()=>$user);$app->instance('request',$request);app('view')->share('errors',new Illuminate\Support\ViewErrorBag());
  $result=$app->make(App\Http\Controllers\AuthController::class)->cargarModulo($request,ltrim($path,'/'));
  if($result instanceof Illuminate\Contracts\View\View){$html=$result->render();$rows[]=['path'=>$path,'status'=>'rendered','view'=>$result->name(),'shell'=>str_contains($html,'sigtram-modulebar'),'pending'=>$result->name()==='mod_gm.mod_tram_exte.stub_pendiente','bytes'=>strlen($html)];}
  else {$rows[]=['path'=>$path,'status'=>'response','code'=>method_exists($result,'getStatusCode')?$result->getStatusCode():null];}
 }catch(Throwable $e){$rows[]=['path'=>$path,'status'=>'error','error'=>get_class($e),'message'=>substr(preg_replace('/SQL:.*$/s','SQL omitido',$e->getMessage()),0,160)];}
 finally{DB::rollBack();}
 echo $path.' '.$rows[array_key_last($rows)]['status'].PHP_EOL;
}
file_put_contents(base_path('docs/frontend/auditoria-render-modulos.json'),json_encode(['at'=>date('c'),'read_only'=>true,'results'=>$rows,'skipped'=>$skipped],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
