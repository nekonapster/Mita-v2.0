<?php

namespace Database\Seeders;

use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Ausencia;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Incidencia;
use App\Models\Instalacion;
use App\Models\MovimientoEconomico;
use App\Models\NecesidadServicio;
use App\Models\Proyecto;
use App\Models\RegistroHora;
use App\Models\Role;
use App\Models\TarifaCoste;
use App\Models\Turno;
use App\Models\User;
use App\Services\ShiftAssignmentValidator;
use App\Services\ShiftGenerationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $rolesData = [
            Role::ADMIN => ['display_name' => 'Administrador', 'description' => 'Acceso total y configuración del sistema'],
            Role::RRHH => ['display_name' => 'Recursos Humanos', 'description' => 'Gestión laboral, contratos, ausencias e incidencias'],
            Role::PLANNER => ['display_name' => 'Planificador', 'description' => 'Gestión de clientes, servicios, turnos y cuadrantes'],
            Role::ACCOUNTANT => ['display_name' => 'Contable', 'description' => 'Control de costes, horas validadas y finanzas'],
            Role::EMPLOYEE => ['display_name' => 'Empleado', 'description' => 'Portal de empleado: consulta de turnos, imputación de horas y ausencias'],
        ];

        $roles = [];
        foreach ($rolesData as $name => $info) {
            $roles[$name] = Role::firstOrCreate(['name' => $name], $info);
        }

        // 2. Categorías profesionales
        $catSocorrista = Categoria::create([
            'nombre' => 'Socorrista Acuático',
            'codigo' => 'SOC',
            'descripcion' => 'Vigilancia y prevención en instalaciones acuáticas y primeros auxilios',
            'activo' => true,
        ]);

        $catMonitor = Categoria::create([
            'nombre' => 'Monitor de Natación',
            'codigo' => 'MON-NAT',
            'descripcion' => 'Enseñanza de natación para todas las edades y actividades acuáticas dirigidas',
            'activo' => true,
        ]);

        $catCoordinador = Categoria::create([
            'nombre' => 'Coordinador de Instalación',
            'codigo' => 'COORD',
            'descripcion' => 'Supervisión operativa de equipos, cuadrantes y relación con el cliente',
            'activo' => true,
        ]);

        $catAdmin = Categoria::create([
            'nombre' => 'Personal Administrativo',
            'codigo' => 'ADM',
            'descripcion' => 'Gestión de documentación laboral y trámites de oficina',
            'activo' => true,
        ]);

        // 3. Actividades
        $actSocorrismo = Actividad::create([
            'nombre' => 'Socorrismo y Vigilancia Acuática',
            'descripcion' => 'Control de bañistas y primeros auxilios en vaso principal y recreativo',
            'activo' => true,
        ]);

        $actNatacionInfantil = Actividad::create([
            'nombre' => 'Cursos de Natación Infantil',
            'descripcion' => 'Cursos formativos de iniciación y perfeccionamiento acuático infantil',
            'activo' => true,
        ]);

        $actAquagym = Actividad::create([
            'nombre' => 'Aquagym y Gimnasia Acuática',
            'descripcion' => 'Clases dirigidas para adultos y mayores',
            'activo' => true,
        ]);

        $actVigilanciaNocturna = Actividad::create([
            'nombre' => 'Vigilancia y Cierre de Instalación',
            'descripcion' => 'Control perimetral nocturno de vasos y dependencias técnicas',
            'activo' => true,
        ]);

        // 4. Clientes e Instalaciones
        $clienteEscorial = Cliente::create([
            'razon_social' => 'Ayuntamiento de San Lorenzo de El Escorial',
            'nif_cif' => 'P2813100A',
            'persona_contacto' => 'Marta Domínguez (Concejalía Deportes)',
            'email' => 'deportes@sanlorenzodeelescorial.org',
            'telefono' => '918905000',
            'direccion' => 'Plaza de la Constitución, 1, 28200 San Lorenzo de El Escorial, Madrid',
            'activo' => true,
            'observaciones' => 'Contrato público de salvamento y cursos municipales',
        ]);

        $instZaburdon = Instalacion::create([
            'cliente_id' => $clienteEscorial->id,
            'nombre' => 'Polideportivo Zaburdón (Piscina Climatizada)',
            'direccion' => 'Las Pozas, 183, 28200 San Lorenzo de El Escorial, Madrid',
            'persona_contacto' => 'Javier Morales (Encargado instalación)',
            'telefono' => '918903377',
            'activo' => true,
            'observaciones' => 'Vaso principal de 25m y vaso de chapoteo',
        ]);

        $instArroyos = Instalacion::create([
            'cliente_id' => $clienteEscorial->id,
            'nombre' => 'Piscina de Verano Los Arroyos',
            'direccion' => 'Calle Principal, s/n, Los Arroyos, Madrid',
            'persona_contacto' => 'Carmen Vega',
            'telefono' => '918901234',
            'activo' => true,
            'observaciones' => 'Abierta únicamente de junio a septiembre',
        ]);

        $clienteLasRozas = Cliente::create([
            'razon_social' => 'Club Deportivo Las Rozas',
            'nif_cif' => 'G84930219',
            'persona_contacto' => 'Alberto Serrano (Gerente)',
            'email' => 'gerencia@cdlasrozas.es',
            'telefono' => '916370011',
            'direccion' => 'Av. del Polideportivo, 22, 28231 Las Rozas de Madrid',
            'activo' => true,
            'observaciones' => 'Entidad privada deportiva',
        ]);

        $instLasRozas = Instalacion::create([
            'cliente_id' => $clienteLasRozas->id,
            'nombre' => 'Centro Acuático Las Rozas',
            'direccion' => 'Av. del Polideportivo, 22, 28231 Las Rozas de Madrid',
            'persona_contacto' => 'Raúl Gómez',
            'telefono' => '916370012',
            'activo' => true,
            'observaciones' => 'Piscina olímpica de 50m y sala de fitness',
        ]);

        // 5. Empleados
        $empCarlos = Empleado::create([
            'nombre' => 'Carlos',
            'apellidos' => 'García Morales',
            'dni_nie' => '47201928A',
            'email' => 'carlos.garcia@mita.es',
            'telefono' => '600112233',
            'categoria_id' => $catSocorrista->id,
            'activo' => true,
            'observaciones' => 'Socorrista experimentado con titulación DESA y reciclaje vigente',
        ]);

        $empLaura = Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz Benítez',
            'dni_nie' => '50392817B',
            'email' => 'laura.ruiz@mita.es',
            'telefono' => '611223344',
            'categoria_id' => $catSocorrista->id,
            'activo' => true,
            'observaciones' => 'Socorrista de verano',
        ]);

        $empDavid = Empleado::create([
            'nombre' => 'David',
            'apellidos' => 'Martín Romero',
            'dni_nie' => '03928172C',
            'email' => 'david.martin@mita.es',
            'telefono' => '622334455',
            'categoria_id' => $catMonitor->id,
            'activo' => true,
            'observaciones' => 'Especialista en cursos de bebés y natación terapéutica',
        ]);

        $empElena = Empleado::create([
            'nombre' => 'Elena',
            'apellidos' => 'Navarro Gómez',
            'dni_nie' => '53918273D',
            'email' => 'elena.navarro@mita.es',
            'telefono' => '633445566',
            'categoria_id' => $catMonitor->id,
            'activo' => true,
            'observaciones' => 'Monitora y socorrista acreditada',
        ]);

        $empPablo = Empleado::create([
            'nombre' => 'Pablo',
            'apellidos' => 'Fernández Gil',
            'dni_nie' => '02918264E',
            'email' => 'pablo.fernandez@mita.es',
            'telefono' => '644556677',
            'categoria_id' => $catCoordinador->id,
            'activo' => true,
            'observaciones' => 'Coordinador general de zona noroeste',
        ]);

        // 6. Contratos laborales
        Contrato::create([
            'empleado_id' => $empCarlos->id,
            'tipo_contrato' => 'Indefinido a tiempo completo',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => null,
            'jornada_semanal_horas' => 40.00,
            'observaciones' => 'Contrato indefinido ordinario',
        ]);

        Contrato::create([
            'empleado_id' => $empLaura->id,
            'tipo_contrato' => 'Fijo Discontinuo',
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-10-31',
            'jornada_semanal_horas' => 35.00,
            'observaciones' => 'Campaña de verano 2026',
        ]);

        Contrato::create([
            'empleado_id' => $empDavid->id,
            'tipo_contrato' => 'Indefinido a tiempo parcial',
            'fecha_inicio' => '2025-09-01',
            'fecha_fin' => null,
            'jornada_semanal_horas' => 25.00,
            'observaciones' => 'Tardes de natación',
        ]);

        Contrato::create([
            'empleado_id' => $empElena->id,
            'tipo_contrato' => 'Indefinido a tiempo completo',
            'fecha_inicio' => '2025-02-01',
            'fecha_fin' => null,
            'jornada_semanal_horas' => 40.00,
            'observaciones' => 'Jornada partida',
        ]);

        Contrato::create([
            'empleado_id' => $empPablo->id,
            'tipo_contrato' => 'Indefinido a tiempo completo',
            'fecha_inicio' => '2024-01-01',
            'fecha_fin' => null,
            'jornada_semanal_horas' => 40.00,
            'observaciones' => 'Coordinación y gestión de equipos',
        ]);

        // 7. Tarifas de coste (TarifasCoste)
        // Por categoría
        TarifaCoste::create([
            'empleado_id' => null,
            'categoria_id' => $catSocorrista->id,
            'coste_hora' => 14.50,
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => null,
            'observaciones' => 'Tarifa de convenio socorrismo 2025/2026',
        ]);

        TarifaCoste::create([
            'empleado_id' => null,
            'categoria_id' => $catMonitor->id,
            'coste_hora' => 16.00,
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => null,
            'observaciones' => 'Tarifa estándar monitores',
        ]);

        TarifaCoste::create([
            'empleado_id' => null,
            'categoria_id' => $catCoordinador->id,
            'coste_hora' => 21.00,
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => null,
            'observaciones' => 'Tarifa de coordinación',
        ]);

        // Específica para Carlos (plus experiencia)
        TarifaCoste::create([
            'empleado_id' => $empCarlos->id,
            'categoria_id' => null,
            'coste_hora' => 15.75,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
            'observaciones' => 'Plus de antigüedad y DESA',
        ]);

        // 8. Usuarios del sistema
        $userAdmin = User::create([
            'name' => 'Administrador MiTa',
            'email' => 'admin@mita.es',
            'password' => Hash::make('password123'),
        ]);
        $userAdmin->roles()->attach($roles[Role::ADMIN]->id);

        $userRrhh = User::create([
            'name' => 'Silvia RRHH',
            'email' => 'rrhh@mita.es',
            'password' => Hash::make('password123'),
        ]);
        $userRrhh->roles()->attach($roles[Role::RRHH]->id);

        $userPlanner = User::create([
            'name' => 'Marcos Planificador',
            'email' => 'planificador@mita.es',
            'password' => Hash::make('password123'),
        ]);
        $userPlanner->roles()->attach($roles[Role::PLANNER]->id);

        $userAccountant = User::create([
            'name' => 'Lucía Contabilidad',
            'email' => 'contable@mita.es',
            'password' => Hash::make('password123'),
        ]);
        $userAccountant->roles()->attach($roles[Role::ACCOUNTANT]->id);

        // Usuario para empleado Carlos García
        $userCarlos = User::create([
            'name' => 'Carlos García Morales',
            'email' => 'carlos@mita.es',
            'password' => Hash::make('password123'),
            'employee_id' => $empCarlos->id,
        ]);
        $userCarlos->roles()->attach($roles[Role::EMPLOYEE]->id);

        // Usuario para empleada Laura Ruiz
        $userLaura = User::create([
            'name' => 'Laura Ruiz Benítez',
            'email' => 'laura@mita.es',
            'password' => Hash::make('password123'),
            'employee_id' => $empLaura->id,
        ]);
        $userLaura->roles()->attach($roles[Role::EMPLOYEE]->id);

        // 9. Proyectos
        $proyectoEscorial = Proyecto::create([
            'cliente_id' => $clienteEscorial->id,
            'nombre' => 'Servicio Integral de Socorrismo y Cursos - El Escorial 2026',
            'descripcion' => 'Contrato anual para la gestión de socorristas y monitores en el Polideportivo Zaburdón y Los Arroyos',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'estado' => Proyecto::ESTADO_ACTIVO,
            'observaciones' => 'Facturación mensual por certificación de horas y canon fijo',
        ]);
        $proyectoEscorial->instalaciones()->attach([$instZaburdon->id, $instArroyos->id]);

        $proyectoLasRozas = Proyecto::create([
            'cliente_id' => $clienteLasRozas->id,
            'nombre' => 'Escuela de Natación y Aquagym Las Rozas 2026',
            'descripcion' => 'Impartición de programas de enseñanza de natación y actividades acuáticas para socios',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2027-06-30',
            'estado' => Proyecto::ESTADO_ACTIVO,
            'observaciones' => 'Programa formativo de temporada escolar',
        ]);
        $proyectoLasRozas->instalaciones()->attach([$instLasRozas->id]);

        // 10. Necesidades de servicio
        $necesidadSocorrismo = NecesidadServicio::create([
            'proyecto_id' => $proyectoEscorial->id,
            'instalacion_id' => $instZaburdon->id,
            'actividad_id' => $actSocorrismo->id,
            'categoria_id' => $catSocorrista->id,
            'fecha_inicio_vigencia' => '2026-01-01',
            'fecha_fin_vigencia' => '2026-12-31',
            'dias_semana' => [1, 2, 3, 4, 5], // Lunes a Viernes
            'hora_inicio' => '10:00:00',
            'hora_fin' => '18:00:00',
            'cruza_medianoche' => false,
            'plazas_necesarias' => 2,
            'observaciones' => 'Turno principal de vigilancia en piscina cubierta. Requiere 2 socorristas simultáneos.',
            'activo' => true,
        ]);

        $necesidadCursos = NecesidadServicio::create([
            'proyecto_id' => $proyectoEscorial->id,
            'instalacion_id' => $instZaburdon->id,
            'actividad_id' => $actNatacionInfantil->id,
            'categoria_id' => $catMonitor->id,
            'fecha_inicio_vigencia' => '2026-01-01',
            'fecha_fin_vigencia' => '2026-12-31',
            'dias_semana' => [1, 2, 3, 4], // Lunes a Jueves
            'hora_inicio' => '17:00:00',
            'hora_fin' => '20:00:00',
            'cruza_medianoche' => false,
            'plazas_necesarias' => 1,
            'observaciones' => 'Cursos de tarde para niños',
            'activo' => true,
        ]);

        $necesidadNocturna = NecesidadServicio::create([
            'proyecto_id' => $proyectoEscorial->id,
            'instalacion_id' => $instZaburdon->id,
            'actividad_id' => $actVigilanciaNocturna->id,
            'categoria_id' => $catSocorrista->id,
            'fecha_inicio_vigencia' => '2026-01-01',
            'fecha_fin_vigencia' => '2026-12-31',
            'dias_semana' => [5, 6], // Viernes y Sábado
            'hora_inicio' => '22:00:00',
            'hora_fin' => '06:00:00',
            'cruza_medianoche' => true,
            'plazas_necesarias' => 1,
            'observaciones' => 'Turno especial nocturno de fin de semana que cruza la medianoche',
            'activo' => true,
        ]);

        // 11. Generar turnos para la semana actual y próxima usando ShiftGenerationService
        $shiftGenService = new ShiftGenerationService();
        $hoy = Carbon::now('Europe/Madrid');
        $lunesActual = $hoy->copy()->startOfWeek();
        $domingoProximo = $hoy->copy()->addWeeks(2)->endOfWeek();

        $resSoc = $shiftGenService->generarTurnos($necesidadSocorrismo, $lunesActual, $domingoProximo, Turno::ESTADO_PUBLICADO);
        $resCur = $shiftGenService->generarTurnos($necesidadCursos, $lunesActual, $domingoProximo, Turno::ESTADO_PUBLICADO);
        $resNoc = $shiftGenService->generarTurnos($necesidadNocturna, $lunesActual, $domingoProximo, Turno::ESTADO_PUBLICADO);

        // 12. Asignaciones de personal
        // Asignar turnos de socorrismo
        $turnosSocorrismo = Turno::where('necesidad_servicio_id', $necesidadSocorrismo->id)->orderBy('fecha')->get();

        foreach ($turnosSocorrismo as $index => $turno) {
            // Asignar a Carlos en todos los turnos de socorrismo
            $asig1 = Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $empCarlos->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);

            // En los primeros 3 turnos, asignar también a Laura (cubierto 100%: 2 de 2 plazas)
            if ($index < 3) {
                $asig2 = Asignacion::create([
                    'turno_id' => $turno->id,
                    'empleado_id' => $empLaura->id,
                    'estado' => Asignacion::ESTADO_CONFIRMADO,
                ]);

                // Si el turno ya pasó o es de los primeros días, crear registro de horas realizadas
                if ($turno->fecha < $hoy->toDateString()) {
                    $inicioReal = Carbon::parse($turno->hora_inicio);
                    $finReal = Carbon::parse($turno->hora_fin);
                    RegistroHora::create([
                        'asignacion_id' => $asig1->id,
                        'inicio_real' => $inicioReal,
                        'fin_real' => $finReal,
                        'descanso_minutos' => 30,
                        'horas_computables' => 7.50,
                        'observaciones' => 'Jornada realizada con normalidad. 30 min de comida.',
                        'estado' => RegistroHora::ESTADO_VALIDADO,
                        'validado_por' => $userPlanner->id,
                        'validado_en' => now(),
                    ]);

                    RegistroHora::create([
                        'asignacion_id' => $asig2->id,
                        'inicio_real' => $inicioReal,
                        'fin_real' => $finReal,
                        'descanso_minutos' => 30,
                        'horas_computables' => 7.50,
                        'observaciones' => 'Puntualidad correcta',
                        'estado' => RegistroHora::ESTADO_PENDIENTE, // Pendiente de revisión
                    ]);
                }
            }
            // En el 4º turno dejamos solo a Carlos (parcialmente cubierto: 1 de 2 plazas)
            // A partir del 5º turno algunos quedan sin cubrir para la demostración
        }

        // Asignar turnos de cursos de natación a David Martín
        $turnosCursos = Turno::where('necesidad_servicio_id', $necesidadCursos->id)->orderBy('fecha')->get();
        foreach ($turnosCursos as $turno) {
            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $empDavid->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // 13. Ausencia aprobada que provoca conflicto sobrevenido para Laura Ruiz
        // Elegimos el turno 2 de socorrismo para Laura
        if (isset($turnosSocorrismo[1])) {
            $turnoAfectado = $turnosSocorrismo[1];
            $ausenciaConflicto = Ausencia::create([
                'empleado_id' => $empLaura->id,
                'tipo' => Ausencia::TIPO_PERMISO,
                'fecha_hora_inicio' => Carbon::parse($turnoAfectado->hora_inicio)->subHour(),
                'fecha_hora_fin' => Carbon::parse($turnoAfectado->hora_fin)->addHour(),
                'estado' => Ausencia::ESTADO_APROBADA,
                'observaciones' => 'Permiso por examen oficial universitario',
                'aprobado_por' => $userRrhh->id,
                'aprobado_en' => now(),
            ]);

            // Detección y marcado automático de conflicto
            $validator = new ShiftAssignmentValidator();
            $validator->marcarConflictosPorAusencia($ausenciaConflicto);
        }

        // 14. Movimientos económicos
        MovimientoEconomico::create([
            'proyecto_id' => $proyectoEscorial->id,
            'instalacion_id' => $instZaburdon->id,
            'tipo' => MovimientoEconomico::TIPO_INGRESO,
            'fecha' => '2026-06-05',
            'concepto' => 'Facturación mensual servicios de socorrismo y cursos - Mayo',
            'importe' => 8450.00,
            'observaciones' => 'Certificación nº 05/2026 abonada por el Ayuntamiento',
            'registrado_por' => $userAccountant->id,
        ]);

        MovimientoEconomico::create([
            'proyecto_id' => $proyectoEscorial->id,
            'instalacion_id' => $instZaburdon->id,
            'tipo' => MovimientoEconomico::TIPO_INGRESO,
            'fecha' => '2026-07-05',
            'concepto' => 'Facturación mensual servicios de socorrismo y cursos - Junio',
            'importe' => 9200.00,
            'observaciones' => 'Certificación nº 06/2026 con incremento horario de verano',
            'registrado_por' => $userAccountant->id,
        ]);

        MovimientoEconomico::create([
            'proyecto_id' => $proyectoEscorial->id,
            'instalacion_id' => $instZaburdon->id,
            'tipo' => MovimientoEconomico::TIPO_GASTO,
            'fecha' => '2026-06-10',
            'concepto' => 'Reposición de material de rescate, botiquín y collarines',
            'importe' => 485.50,
            'observaciones' => 'Material consumible homologado para socorristas',
            'registrado_por' => $userAccountant->id,
        ]);

        MovimientoEconomico::create([
            'proyecto_id' => $proyectoEscorial->id,
            'instalacion_id' => $instZaburdon->id,
            'tipo' => MovimientoEconomico::TIPO_GASTO,
            'fecha' => '2026-06-22',
            'concepto' => 'Material didáctico para cursos de natación (tablas y corcheras)',
            'importe' => 320.00,
            'observaciones' => 'Material pedagógico para los cursos infantiles',
            'registrado_por' => $userAccountant->id,
        ]);

        // 15. Incidencias
        Incidencia::create([
            'titulo' => 'Desperfecto en tensor de corchera calle 3',
            'descripcion' => 'Durante la sesión de natación se soltó el cable tensor. Requiere tensado y sustitución de mosquetón de seguridad.',
            'fecha' => now()->toDateString(),
            'empleado_id' => $empCarlos->id,
            'instalacion_id' => $instZaburdon->id,
            'proyecto_id' => $proyectoEscorial->id,
            'estado' => Incidencia::ESTADO_ABIERTA,
            'user_id' => $userCarlos->id,
        ]);

        Incidencia::create([
            'titulo' => 'Revisión y sustitución de parches DESA',
            'descripcion' => 'Revisión periódica completada con éxito. Electrodos sustituidos con fecha de caducidad actualizada a 2028.',
            'fecha' => now()->subDays(5)->toDateString(),
            'empleado_id' => $empPablo->id,
            'instalacion_id' => $instZaburdon->id,
            'proyecto_id' => $proyectoEscorial->id,
            'estado' => Incidencia::ESTADO_RESUELTA,
            'user_id' => $userPlanner->id,
        ]);
    }
}
