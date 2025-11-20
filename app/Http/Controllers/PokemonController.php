<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PokemonController extends Controller
{
    // Traducciones
    private $tiposTraduccion = [
        'normal' => 'Normal', 'fire' => 'Fuego', 'water' => 'Agua',
        'electric' => 'Eléctrico', 'grass' => 'Planta', 'ice' => 'Hielo',
        'fighting' => 'Lucha', 'poison' => 'Veneno', 'ground' => 'Tierra',
        'flying' => 'Volador', 'psychic' => 'Psíquico', 'bug' => 'Bicho',
        'rock' => 'Roca', 'ghost' => 'Fantasma', 'dragon' => 'Dragón',
        'dark' => 'Siniestro', 'steel' => 'Acero', 'fairy' => 'Hada'
    ];

    private $estadisticasTraduccion = [
        'hp' => 'PS', 'attack' => 'Ataque', 'defense' => 'Defensa',
        'special-attack' => 'At. Especial', 'special-defense' => 'Def. Especial',
        'speed' => 'Velocidad'
    ];

    public function index(Request $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        
        $filtroTipo = $request->get('tipo');
        $filtroRegion = $request->get('region');
        
        // Si hay filtros, usar método simplificado
        if ($filtroTipo || $filtroRegion) {
            return $this->obtenerPokemonFiltrados($filtroTipo, $filtroRegion);
        }
        
        // Sin filtros: paginación normal
        $limite = 1025;
        $porPagina = 20;
        $pagina = $request->get('pagina', 1);
        $inicio = ($pagina - 1) * $porPagina + 1;
        $fin = min($inicio + $porPagina - 1, $limite);

        $pokemones = $this->cargarPokemones($inicio, $fin);

        $totalPaginas = ceil($limite / $porPagina);
        $todosLosTipos = array_keys($this->tiposTraduccion);
        $todasLasRegiones = ['kanto', 'johto', 'hoenn', 'sinnoh', 'unova', 'kalos', 'alola', 'galar', 'paldea'];

        return view('pokemon.index', compact('pokemones', 'pagina', 'totalPaginas', 'todosLosTipos', 'todasLasRegiones', 'filtroTipo', 'filtroRegion'));
    }
    
    private function obtenerPokemonFiltrados($filtroTipo, $filtroRegion)
    {
        $cacheKey = "pokemon_filtro_" . ($filtroTipo ?? 'all') . "_" . ($filtroRegion ?? 'all');
        
        $pokemones = Cache::remember($cacheKey, 7200, function() use ($filtroTipo, $filtroRegion) {
            $resultado = [];
            
            // Obtener lista completa de Pokémon
            $listResponse = Http::timeout(10)->get("https://pokeapi.co/api/v2/pokemon?limit=1025");
            if (!$listResponse->successful()) {
                return [];
            }
            
            $listaPokemon = $listResponse->json()['results'];
            
            foreach ($listaPokemon as $index => $poke) {
                $id = $index + 1;
                
                try {
                    // Cargar desde caché si existe
                    $pokemon = Cache::remember("pokemon_basic_{$id}", 7200, function() use ($id) {
                        $resp = Http::timeout(5)->get("https://pokeapi.co/api/v2/pokemon/{$id}");
                        return $resp->successful() ? $resp->json() : null;
                    });
                    
                    if (!$pokemon) continue;
                    
                    $especie = Cache::remember("species_basic_{$id}", 7200, function() use ($pokemon) {
                        $resp = Http::timeout(5)->get($pokemon['species']['url']);
                        return $resp->successful() ? $resp->json() : null;
                    });
                    
                    if (!$especie) continue;
                    
                    // Verificar filtros
                    $tiposPokemon = array_map(fn($t) => $t['type']['name'], $pokemon['types']);
                    
                    // Filtro de tipo
                    if ($filtroTipo && !in_array($filtroTipo, $tiposPokemon)) {
                        continue;
                    }
                    
                    // Filtro de región
                    $generacion = $especie['generation']['name'];
                    $regionPokemon = $this->generacionARegion($generacion);
                    
                    if ($filtroRegion && $regionPokemon !== $filtroRegion) {
                        continue;
                    }
                    
                    // Si pasa los filtros, procesar
                    $resultado[] = $this->procesarPokemon($pokemon, $especie, $id);
                    
                } catch (\Exception $e) {
                    \Log::error("Error procesando Pokémon {$id}: " . $e->getMessage());
                    continue;
                }
            }
            
            return $resultado;
        });
        
        $pagina = null;
        $totalPaginas = null;
        $todosLosTipos = array_keys($this->tiposTraduccion);
        $todasLasRegiones = ['kanto', 'johto', 'hoenn', 'sinnoh', 'unova', 'kalos', 'alola', 'galar', 'paldea'];
        
        return view('pokemon.index', compact('pokemones', 'pagina', 'totalPaginas', 'todosLosTipos', 'todasLasRegiones', 'filtroTipo', 'filtroRegion'));
    }
    
    private function cargarPokemones($inicio, $fin)
    {
        $pokemones = [];
        
        $responses = Http::pool(fn ($pool) => collect(range($inicio, $fin))->map(
            fn ($i) => $pool->as($i)->timeout(5)->get("https://pokeapi.co/api/v2/pokemon/{$i}")
        )->toArray());

        foreach ($responses as $id => $response) {
            try {
                if (!$response->successful()) continue;
                
                $pokemon = $response->json();
                
                $respuestaEspecie = Http::timeout(5)->get($pokemon['species']['url']);
                if (!$respuestaEspecie->successful()) continue;
                $especie = $respuestaEspecie->json();
                
                $pokemones[] = $this->procesarPokemon($pokemon, $especie, $id);
                
            } catch (\Exception $e) {
                \Log::error("Error procesando Pokémon {$id}: " . $e->getMessage());
                continue;
            }
        }
        
        return $pokemones;
    }
    
    private function procesarPokemon($pokemon, $especie, $id)
    {
        // Cadena evolutiva
        $cadenaEvolutiva = [];
        if (isset($especie['evolution_chain']['url'])) {
            $cadenaEvolutiva = Cache::remember("evolution_{$id}", 7200, function() use ($especie) {
                try {
                    $resp = Http::timeout(5)->get($especie['evolution_chain']['url']);
                    if ($resp->successful()) {
                        return $this->extraerCadenaEvolutiva($resp->json()['chain']);
                    }
                } catch (\Exception $e) {
                    return [];
                }
                return [];
            });
        }
        
        $tiposPokemon = array_map(fn($t) => $t['type']['name'], $pokemon['types']);
        
        // Info del tipo
        $infoPrimerTipo = Cache::remember("type_info_{$tiposPokemon[0]}", 7200, function() use ($tiposPokemon) {
            return $this->obtenerInfoTipo($tiposPokemon[0]);
        });
        
        $infoRegion = $this->obtenerRegionDesdeGeneracion($especie['generation']['name']);
        
        $descripcion = collect($especie['flavor_text_entries'])
            ->firstWhere('language.name', 'es');
        if (!$descripcion) {
            $descripcion = collect($especie['flavor_text_entries'])->first();
        }
        
        $habilidades = array_map(function($habilidad) {
            return [
                'nombre' => $this->traducirHabilidad($habilidad['ability']['name']),
                'es_oculta' => $habilidad['is_hidden']
            ];
        }, $pokemon['abilities']);
        
        $estadisticas = [];
        foreach ($pokemon['stats'] as $stat) {
            $nombreStat = $stat['stat']['name'];
            $estadisticas[$nombreStat] = [
                'valor' => $stat['base_stat'],
                'nombre_es' => $this->estadisticasTraduccion[$nombreStat] ?? ucfirst($nombreStat)
            ];
        }
        
        return [
            'id' => $pokemon['id'],
            'nombre' => $this->capitalizarNombre($pokemon['name']),
            'nombre_ingles' => $pokemon['name'],
            'imagen' => $pokemon['sprites']['other']['official-artwork']['front_default'] 
                      ?? $pokemon['sprites']['front_default'],
            'tipos' => array_map(fn($t) => [
                'nombre' => $this->tiposTraduccion[$t] ?? ucfirst($t),
                'nombre_ingles' => $t
            ], $tiposPokemon),
            'altura' => $pokemon['height'],
            'peso' => $pokemon['weight'],
            'descripcion' => $descripcion ? str_replace(["\n", "\f", ""], ' ', $descripcion['flavor_text']) : 'No disponible',
            'habilidades' => $habilidades,
            'estadisticas' => $estadisticas,
            'habitat' => $especie['habitat']['name'] ?? 'desconocido',
            'generacion' => str_replace('generation-', 'Gen ', $especie['generation']['name']),
            'evoluciones' => $cadenaEvolutiva,
            'info_tipo' => $infoPrimerTipo,
            'region' => $infoRegion
        ];
    }
    
    private function generacionARegion($generacion)
    {
        $mapa = [
            'generation-i' => 'kanto',
            'generation-ii' => 'johto',
            'generation-iii' => 'hoenn',
            'generation-iv' => 'sinnoh',
            'generation-v' => 'unova',
            'generation-vi' => 'kalos',
            'generation-vii' => 'alola',
            'generation-viii' => 'galar',
            'generation-ix' => 'paldea',
        ];
        
        return $mapa[$generacion] ?? 'unknown';
    }
    
    private function extraerCadenaEvolutiva($cadena)
    {
        $evoluciones = [];
        $actual = $cadena;
        
        while ($actual) {
            $evoluciones[] = $this->capitalizarNombre($actual['species']['name']);
            $actual = $actual['evolves_to'][0] ?? null;
        }
        
        return $evoluciones;
    }
    
    private function obtenerInfoTipo($nombreTipo)
    {
        try {
            $respuesta = Http::timeout(5)->get("https://pokeapi.co/api/v2/type/{$nombreTipo}");
            if (!$respuesta->successful()) {
                return $this->infoTipoPorDefecto($nombreTipo);
            }
            
            $datosTipo = $respuesta->json();
            
            $debilidades = array_map(
                fn($d) => $this->tiposTraduccion[$d['name']] ?? ucfirst($d['name']),
                $datosTipo['damage_relations']['double_damage_from']
            );
            
            $fortalezas = array_map(
                fn($d) => $this->tiposTraduccion[$d['name']] ?? ucfirst($d['name']),
                $datosTipo['damage_relations']['double_damage_to']
            );
            
            return [
                'nombre' => $this->tiposTraduccion[$nombreTipo] ?? ucfirst($nombreTipo),
                'debilidades' => $debilidades,
                'fortalezas' => $fortalezas,
            ];
        } catch (\Exception $e) {
            return $this->infoTipoPorDefecto($nombreTipo);
        }
    }
    
    private function infoTipoPorDefecto($nombreTipo)
    {
        return [
            'nombre' => $this->tiposTraduccion[$nombreTipo] ?? ucfirst($nombreTipo),
            'debilidades' => [],
            'fortalezas' => [],
        ];
    }
    
    private function obtenerRegionDesdeGeneracion($generacion)
    {
        $mapeoRegiones = [
            'generation-i' => ['nombre' => 'Kanto', 'nombre_ingles' => 'kanto'],
            'generation-ii' => ['nombre' => 'Johto', 'nombre_ingles' => 'johto'],
            'generation-iii' => ['nombre' => 'Hoenn', 'nombre_ingles' => 'hoenn'],
            'generation-iv' => ['nombre' => 'Sinnoh', 'nombre_ingles' => 'sinnoh'],
            'generation-v' => ['nombre' => 'Teselia', 'nombre_ingles' => 'unova'],
            'generation-vi' => ['nombre' => 'Kalos', 'nombre_ingles' => 'kalos'],
            'generation-vii' => ['nombre' => 'Alola', 'nombre_ingles' => 'alola'],
            'generation-viii' => ['nombre' => 'Galar', 'nombre_ingles' => 'galar'],
            'generation-ix' => ['nombre' => 'Paldea', 'nombre_ingles' => 'paldea'],
        ];
        
        return $mapeoRegiones[$generacion] ?? ['nombre' => 'Desconocida', 'nombre_ingles' => 'unknown'];
    }
    
    private function traducirHabilidad($nombre)
    {
        return ucwords(str_replace('-', ' ', $nombre));
    }
    
    private function capitalizarNombre($nombre)
    {
        return ucwords(str_replace('-', ' ', $nombre));
    }
}