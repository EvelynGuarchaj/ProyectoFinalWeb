<?php

namespace App\Services;

use App\Models\Oportunidad;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas del reporte del mes (spec 14, L6, R7 y sección 11). Todo se calcula
 * en MySQL con el constructor de consultas: el único dato del usuario que entra
 * a SQL es el mes, siempre como parámetro ?. Los montos salen como texto DECIMAL;
 * PHP no los suma ni los multiplica, solo los formatea para mostrarlos.
 *
 * El pronóstico se suma sin redondear (bruto) y cada fila aplica ROUND una sola
 * vez: el de un grupo es ROUND(SUM(...), 2), no la suma de valores redondeados.
 */
class Reporte
{
    /** Probabilidad por etapa como decimales exactos (sección 11.4). Expresión fija. */
    private const PROBABILIDAD = "CASE o.etapa WHEN 'prospecto' THEN 0.10 WHEN 'calificado' THEN 0.25 "
        ."WHEN 'propuesta' THEN 0.50 WHEN 'negociacion' THEN 0.75 ELSE 0 END";

    /** Ventas, total y pronóstico de un grupo de asesores; ROUND una sola vez. */
    private const SUMAS = 'COALESCE(SUM(v.ventas), 0) AS ventas, COALESCE(SUM(v.total), 0) AS total, '
        .'ROUND(COALESCE(SUM(p.bruto), 0), 2) AS pronostico';

    /**
     * Unidades, total vendido y pronóstico de un grupo de líneas. El CASE de
     * probabilidad da 0 a las ganadas: el pronóstico solo suma abiertas.
     */
    private const SUMAS_LINEAS = "COALESCE(SUM(CASE WHEN o.etapa = 'ganada' THEN l.cantidad END), 0) AS unidades, "
        ."COALESCE(SUM(CASE WHEN o.etapa = 'ganada' THEN l.cantidad * l.precio_unitario END), 0) AS total, "
        .'ROUND(COALESCE(SUM(l.cantidad * l.precio_unitario * ('.self::PROBABILIDAD.')), 0), 2) AS pronostico';

    /**
     * Ventas del mes por asesor (R7): planes ganados cuyo cierre, en hora de
     * Guatemala, cae en el mes. El crédito es de oportunidades.vendedor_id.
     * LEFT JOIN + COUNT(DISTINCT) cuenta cada venta una vez aunque tenga varias
     * líneas. El total usa el precio guardado en la línea. La usa también /equipo.
     */
    public function ventasDelMes(string $mes): Builder
    {
        return DB::table('oportunidades as o')
            ->leftJoin('oportunidad_productos as l', 'l.oportunidad_id', '=', 'o.id')
            ->where('o.etapa', 'ganada')
            ->whereRaw("DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?", [$mes])
            ->groupBy('o.vendedor_id')
            ->selectRaw('o.vendedor_id, COUNT(DISTINCT o.id) AS ventas, SUM(l.cantidad * l.precio_unitario) AS total');
    }

    /**
     * Pronóstico por asesor de sus planes abiertos, sin filtro de mes y SIN
     * redondear: cada fila del reporte redondea una sola vez al final.
     */
    public function pronosticoAbierto(): Builder
    {
        return DB::table('oportunidades as o')
            ->join('oportunidad_productos as l', 'l.oportunidad_id', '=', 'o.id')
            ->whereIn('o.etapa', Oportunidad::ABIERTAS)
            ->groupBy('o.vendedor_id')
            ->selectRaw('o.vendedor_id, SUM(l.cantidad * l.precio_unitario * ('.self::PROBABILIDAD.')) AS bruto');
    }

    /**
     * Una fila por asesor del alcance (activos y desactivados, con o sin ventas):
     * coordinador, cartera, ventas del mes, total vendido y pronóstico.
     * Orden: coordinador y asesor por nombre.
     */
    public function porAsesor(User $quien, string $mes): Collection
    {
        return $this->asesores($quien, $mes)
            ->leftJoin('users as s', 's.id', '=', 'u.supervisor_id')
            ->leftJoinSub($this->carteras(), 'c', 'c.vendedor_id', '=', 'u.id')
            ->select('u.id', 'u.name', 'u.activo', 's.name as coordinador')
            ->selectRaw('COALESCE(c.cartera, 0) AS cartera')
            ->selectRaw('COALESCE(v.ventas, 0) AS ventas, COALESCE(v.total, 0) AS total')
            ->selectRaw('ROUND(COALESCE(p.bruto, 0), 2) AS pronostico')
            ->orderBy('s.name')
            ->orderBy('u.name')
            ->get();
    }

    /**
     * Fila de totales del alcance: "Total del equipo" para el coordinador,
     * "Toda la clínica" para el administrador.
     */
    public function totales(User $quien, string $mes): object
    {
        return $this->asesores($quien, $mes)
            ->selectRaw(self::SUMAS)
            ->first();
    }

    /**
     * Solo administrador: una fila por coordinador (activo o no) con las ventas,
     * el total y el pronóstico de los asesores que tiene hoy. Sin asesores → 0.
     */
    public function porEquipo(string $mes): Collection
    {
        return DB::table('users as s')
            ->leftJoin('users as u', function (JoinClause $j) {
                $j->on('u.supervisor_id', '=', 's.id')->where('u.rol', 'vendedor');
            })
            ->leftJoinSub($this->ventasDelMes($mes), 'v', 'v.vendedor_id', '=', 'u.id')
            ->leftJoinSub($this->pronosticoAbierto(), 'p', 'p.vendedor_id', '=', 'u.id')
            ->where('s.rol', 'supervisor')
            ->groupBy('s.id', 's.name', 's.activo')
            ->select('s.id', 's.name', 's.activo')
            ->selectRaw(self::SUMAS)
            ->orderBy('s.name')
            ->get();
    }

    /**
     * Una fila por tratamiento con movimiento: unidades y total vendidos en el mes
     * y pronóstico de sus líneas en planes abiertos (sin filtro de mes), dentro
     * del alcance. Orden por nombre. El precio es el guardado en la línea; de
     * productos solo se lee el nombre.
     */
    public function porTratamiento(User $quien, string $mes): Collection
    {
        return $this->lineas($quien, $mes)
            ->groupBy('pr.id', 'pr.nombre')
            ->select('pr.nombre')
            ->selectRaw(self::SUMAS_LINEAS)
            ->orderBy('pr.nombre')
            ->get();
    }

    /** Fila "Total" de la tabla por tratamiento: la misma consulta sin agrupar. */
    public function totalPorTratamiento(User $quien, string $mes): object
    {
        return $this->lineas($quien, $mes)
            ->selectRaw(self::SUMAS_LINEAS)
            ->first();
    }

    /**
     * Líneas de los planes del alcance que son ganados del mes o abiertos. Como el
     * where ya deja solo las ganadas de ese mes, SUMAS_LINEAS no repite el mes.
     */
    private function lineas(User $quien, string $mes): Builder
    {
        return DB::table('oportunidad_productos as l')
            ->join('oportunidades as o', 'o.id', '=', 'l.oportunidad_id')
            ->join('productos as pr', 'pr.id', '=', 'l.producto_id')
            ->whereIn('o.vendedor_id', User::asesoresVisiblesPara($quien)->select('id'))
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $g) => $g
                    ->where('o.etapa', 'ganada')
                    ->whereRaw("DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?", [$mes]))
                ->orWhereIn('o.etapa', Oportunidad::ABIERTAS));
    }

    /**
     * Asesores del alcance (u) con sus ventas del mes (v) y su pronóstico (p).
     * El alcance va como subconsulta: el scope usa rol y supervisor_id sin tabla,
     * que serían ambiguos junto al alias s de users.
     */
    private function asesores(User $quien, string $mes): Builder
    {
        return DB::table('users as u')
            ->leftJoinSub($this->ventasDelMes($mes), 'v', 'v.vendedor_id', '=', 'u.id')
            ->leftJoinSub($this->pronosticoAbierto(), 'p', 'p.vendedor_id', '=', 'u.id')
            ->whereIn('u.id', User::asesoresVisiblesPara($quien)->select('id'));
    }

    /** Cartera de cada asesor: cuántos pacientes tiene hoy (sin filtro de mes). */
    private function carteras(): Builder
    {
        return DB::table('clientes')
            ->whereNotNull('vendedor_id')
            ->groupBy('vendedor_id')
            ->selectRaw('vendedor_id, COUNT(*) AS cartera');
    }
}
