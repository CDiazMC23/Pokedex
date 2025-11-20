<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokédex Digital</title>
    <link rel="stylesheet" href="{{ asset('css/pokedex.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="pokedex-container">
        <header class="pokedex-header">
            <div class="header-lights">
                <div class="light light-blue"></div>
                <div class="light light-red"></div>
                <div class="light light-yellow"></div>
                <div class="light light-green"></div>
            </div>
            <h1 class="pokedex-title">POKÉDEX DIGITAL</h1>
            <div class="header-screen">
                <div class="screen-glare"></div>
            </div>
        </header>

        <!-- Filtros -->
        <div class="filtros-container">
            <form method="GET" class="filtros-form">
                <div class="filtro-grupo">
                    <label for="tipo">Filtrar por Tipo:</label>
                    <select name="tipo" id="tipo" onchange="this.form.submit()">
                        <option value="">Todos los tipos</option>
                        @foreach ($todosLosTipos as $tipo)
                            <option value="{{ $tipo }}" {{ $filtroTipo === $tipo ? 'selected' : '' }}>
                                {{ ucfirst($tipo) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filtro-grupo">
                    <label for="region">Filtrar por Región:</label>
                    <select name="region" id="region" onchange="this.form.submit()">
                        <option value="">Todas las regiones</option>
                        @foreach ($todasLasRegiones as $region)
                            <option value="{{ $region }}" {{ $filtroRegion === $region ? 'selected' : '' }}>
                                {{ ucfirst($region) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($filtroTipo || $filtroRegion)
                    <a href="{{ url('/') }}" class="btn-limpiar">Limpiar Filtros</a>
                @endif
            </form>
        </div>

        <div class="pokedex-content">
            <div class="pokemon-grid">
                @forelse ($pokemones as $pokemon)
                    <div class="pokemon-card" data-type="{{ $pokemon['tipos'][0]['nombre_ingles'] }}">
                        <div class="card-header">
                            <span class="pokemon-id">#{{ str_pad($pokemon['id'], 4, '0', STR_PAD_LEFT) }}</span>
                            <div class="type-badges">
                                @foreach ($pokemon['tipos'] as $tipo)
                                    <span class="type-badge type-{{ $tipo['nombre_ingles'] }}">{{ $tipo['nombre'] }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="pokemon-image-container">
                            <div class="pokeball-bg"></div>
                            <img src="{{ $pokemon['imagen'] }}" alt="{{ $pokemon['nombre'] }}" class="pokemon-image" loading="lazy">
                        </div>

                        <div class="card-body">
                            <h2 class="pokemon-name">{{ $pokemon['nombre'] }}</h2>
                            
                            <div class="region-badge">
                                <span class="region-icon">🗺️</span>
                                <span>{{ $pokemon['region']['nombre'] }}</span>
                                <span class="generation-tag">{{ $pokemon['generacion'] }}</span>
                            </div>

                            <div class="pokemon-info">
                                <div class="info-row">
                                    <span class="info-label">Altura:</span>
                                    <span class="info-value">{{ $pokemon['altura'] / 10 }} m</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Peso:</span>
                                    <span class="info-value">{{ $pokemon['peso'] / 10 }} kg</span>
                                </div>
                            </div>

                            <div class="pokemon-description">
                                <p>{{ Str::limit($pokemon['descripcion'], 100) }}</p>
                            </div>

                            <div class="pokemon-stats">
                                @foreach ($pokemon['estadisticas'] as $nombreStat => $stat)
                                    @if(in_array($nombreStat, ['hp', 'attack', 'defense']))
                                        <div class="stat-bar">
                                            <span class="stat-name">{{ $stat['nombre_es'] }}</span>
                                            <div class="stat-progress">
                                                <div class="stat-fill" style="width: {{ ($stat['valor'] / 255) * 100 }}%"></div>
                                            </div>
                                            <span class="stat-value">{{ $stat['valor'] }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <!-- Nivel 4: Información del Tipo -->
                            @if(!empty($pokemon['info_tipo']['debilidades']) || !empty($pokemon['info_tipo']['fortalezas']))
                                <div class="tipo-info">
                                    <h4 class="tipo-info-titulo">Tipo {{ $pokemon['info_tipo']['nombre'] }}</h4>
                                    
                                    @if(!empty($pokemon['info_tipo']['debilidades']))
                                        <div class="tipo-detalle">
                                            <span class="tipo-label debil">Débil contra:</span>
                                            <div class="tipo-lista">
                                                @foreach (array_slice($pokemon['info_tipo']['debilidades'], 0, 3) as $debilidad)
                                                    <span class="tipo-mini">{{ $debilidad }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if(!empty($pokemon['info_tipo']['fortalezas']))
                                        <div class="tipo-detalle">
                                            <span class="tipo-label fuerte">Fuerte contra:</span>
                                            <div class="tipo-lista">
                                                @foreach (array_slice($pokemon['info_tipo']['fortalezas'], 0, 3) as $fortaleza)
                                                    <span class="tipo-mini">{{ $fortaleza }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="pokemon-abilities">
                                <h4 class="abilities-title">Habilidades:</h4>
                                <div class="abilities-list">
                                    @foreach ($pokemon['habilidades'] as $habilidad)
                                        <span class="ability-badge {{ $habilidad['es_oculta'] ? 'hidden-ability' : '' }}">
                                            {{ $habilidad['nombre'] }}
                                            @if($habilidad['es_oculta'])
                                                <span class="hidden-tag">Oculta</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            @if(count($pokemon['evoluciones']) > 1)
                                <div class="evolution-chain">
                                    <h4 class="evolution-title">Evolución:</h4>
                                    <div class="evolution-list">
                                        @foreach ($pokemon['evoluciones'] as $index => $evolucion)
                                            <span class="evolution-name {{ $evolucion === $pokemon['nombre'] ? 'current' : '' }}">
                                                {{ $evolucion }}
                                            </span>
                                            @if($index < count($pokemon['evoluciones']) - 1)
                                                <span class="evolution-arrow">→</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="no-results">
                        <p>No se encontraron Pokémon con estos filtros</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Paginación -->
        @if(!$filtroTipo && !$filtroRegion)
        <div class="paginacion-container">
            <div class="paginacion">
                @if($pagina > 1)
                    <a href="?pagina={{ $pagina - 1 }}" class="btn-pag">
                        ← Anterior
                    </a>
                @endif

                <span class="pagina-actual">
                    Página {{ $pagina }} de {{ $totalPaginas }}
                </span>

                @if($pagina < $totalPaginas)
                    <a href="?pagina={{ $pagina + 1 }}" class="btn-pag">
                        Siguiente →
                    </a>
                @endif
            </div>

            <div class="salto-pagina">
                <form method="GET">
                    <label>Ir a página:</label>
                    <input type="number" name="pagina" min="1" max="{{ $totalPaginas }}" value="{{ $pagina }}">
                    <button type="submit" class="btn-ir">Ir</button>
                </form>
            </div>
        </div>
        @endif

        <footer class="pokedex-footer">
            <div class="footer-buttons">
                <button class="control-btn">◀</button>
                <button class="control-btn control-center">●</button>
                <button class="control-btn">▶</button>
            </div>
            <p class="footer-text">
                @if($filtroTipo || $filtroRegion)
                    Mostrando {{ count($pokemones) }} Pokémon filtrados
                    @if($filtroTipo) de tipo <strong>{{ ucfirst($filtroTipo) }}</strong>@endif
                    @if($filtroRegion) de la región <strong>{{ ucfirst($filtroRegion) }}</strong>@endif
                @else
                    Mostrando {{ count($pokemones) }} Pokémon | Total: 1025 Pokémon
                @endif
            </p>
        </footer>
    </div>

    <script>
        // Animación de las barras de estadísticas cuando se cargan
        document.addEventListener('DOMContentLoaded', function() {
            const statBars = document.querySelectorAll('.stat-fill');
            statBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>