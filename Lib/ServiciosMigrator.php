<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\FS2017Migrator\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Description of ServiciosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ServiciosMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (false === $this->dataBase->tableExists('servicioscli') || false === \class_exists('\FacturaScripts\Dinamic\Model\ServicioAT')) {
            return true;
        }

        $sql = 'SELECT * FROM servicioscli ORDER BY idservicio ASC';
        foreach ($this->dataBase->selectLimit($sql, 300, $offset) as $row) {
            if (false === $this->newServicio($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * 
     * @param array $row
     *
     * @return bool
     */
    private function newServicio($row): bool
    {
        $servicio = new \FacturaScripts\Plugins\Servicios\Model\ServicioAT();
        if ($servicio->loadFromCode($row['idservicio'])) {
            return true;
        }

        $servicio->codagente = $row['codagente'];
        $servicio->codalmacen = $row['codalmacen'];
        $servicio->codcliente = $row['codcliente'];
        $servicio->descripcion = $row['descripcion'];
        $servicio->fecha = $row['fecha'];
        $servicio->hora = $row['hora'];
        $servicio->idempresa = $this->toolBox()->appSettings()->get('default', 'idempresa');
        $servicio->idservicio = (int) $row['idservicio'];
        $servicio->observaciones = $row['observaciones'];

        if (\in_array($row['idestado'], ['1', '2'])) {
            $servicio->idestado = (int) $row['idestado'];
        } else {
            $servicio->idestado = 3;
        }

        if (false === $servicio->save()) {
            return false;
        }

        return $this->migrateLines($servicio, (bool) $row['idalbaran']) &&
            $this->migrateDetails($servicio) &&
            $this->linkInvoice($servicio, $row);
    }

    /**
     * 
     * @param \FacturaScripts\Plugins\Servicios\Model\ServicioAT $servicio
     * @param array                                              $row
     *
     * @return bool
     */
    private function linkInvoice($servicio, $row): bool
    {
        if (empty($row['idalbaran'])) {
            return true;
        }

        $albaran = new AlbaranCliente();
        if (false === $albaran->loadFromCode($row['idalbaran'])) {
            return true;
        }

        foreach ($albaran->childrenDocuments() as $child) {
            $child->idservicio = $servicio->idservicio;
            return $child->save();
        }

        return true;
    }

    /**
     * 
     * @param \FacturaScripts\Plugins\Servicios\Model\ServicioAT $servicio
     *
     * @return bool
     */
    private function migrateDetails($servicio): bool
    {
        $sql = 'SELECT * FROM detalles_servicios WHERE idservicio = '
            . $this->dataBase->var2str($servicio->idservicio) . ' ORDER BY id ASC';

        foreach ($this->dataBase->select($sql) as $row) {
            $newTrabajo = new \FacturaScripts\Plugins\Servicios\Model\TrabajoAT();
            $newTrabajo->descripcion = $row['descripcion'] . ' #' . $row['nick'];
            $newTrabajo->fechainicio = \date(Variante::DATE_STYLE, \strtotime($row['fecha']));
            $newTrabajo->horainicio = $row['hora'];
            $newTrabajo->idservicio = $servicio->idservicio;
            $newTrabajo->estado = \FacturaScripts\Plugins\Servicios\Model\TrabajoAT::STATUS_NONE;
            if (false === $newTrabajo->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @param \FacturaScripts\Plugins\Servicios\Model\ServicioAT $servicio
     * @param bool                                               $invoice
     *
     * @return bool
     */
    private function migrateLines($servicio, $invoice): bool
    {
        $sql = 'SELECT * FROM lineasservicioscli WHERE idservicio = '
            . $this->dataBase->var2str($servicio->idservicio) . ' ORDER BY idlinea ASC';

        foreach ($this->dataBase->select($sql) as $row) {
            $newTrabajo = new \FacturaScripts\Plugins\Servicios\Model\TrabajoAT();
            $newTrabajo->cantidad = (float) $row['cantidad'];
            $newTrabajo->codagente = $servicio->codagente;
            $newTrabajo->descripcion = $row['descripcion'];
            $newTrabajo->fechainicio = $servicio->fecha;
            $newTrabajo->horainicio = $servicio->hora;
            $newTrabajo->idservicio = $servicio->idservicio;

            if ($newTrabajo->cantidad != 0) {
                $newTrabajo->precio = \floatval($row['pvptotal']) / \floatval($row['cantidad']);
            }

            $variante = new Variante();
            $where = [new DataBaseWhere('referencia', $row['referencia'])];
            if (!empty($row['referencia']) && $variante->loadFromCode('', $where)) {
                $newTrabajo->referencia = $row['referencia'];
            }

            $newTrabajo->estado = $invoice ? \FacturaScripts\Plugins\Servicios\Model\TrabajoAT::STATUS_INVOICED :
                \FacturaScripts\Plugins\Servicios\Model\TrabajoAT::STATUS_NONE;

            if (false === $newTrabajo->save()) {
                return false;
            }
        }

        return true;
    }
}
