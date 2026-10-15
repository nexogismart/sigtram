<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Core\ModuleDispatcher;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WorkspaceIntegrationContractTest extends TestCase
{
    public function test_iframe_aliases_are_backed_by_the_same_registered_controller_actions(): void
    {
        $dispatcher = new ModuleDispatcher();
        foreach (['correo-interno'=>'correo_interno', 'buscar-tramite'=>'buscar_tramite', 'ingresar-tramite'=>'ingresar_tramite'] as $legacy=>$current) {
            $descriptor = $dispatcher->getDescriptor('mod_gene.mod_tram_inter.'.$legacy);
            $route = Route::getRoutes()->getByName('mod_tram_inter.'.$current);
            $this->assertNotNull($descriptor);
            $this->assertNotNull($route);
            $this->assertSame($descriptor['controller'].'@'.$descriptor['method'], $route->getActionName());
            $this->assertSame('mod_gene/mod_tram_inter/'.$current, $route->uri());
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }
}
