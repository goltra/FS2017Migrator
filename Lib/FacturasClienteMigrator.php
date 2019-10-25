<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\LiquidacionComision;

/**
 * Description of FacturasClienteMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class FacturasClienteMigrator extends FacturasProveedorMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset && !$this->fixLinesTable('lineasfacturascli')) {
            return false;
        }

        if (0 === $offset && !$this->fixCustomers('facturascli')) {
            return false;
        }

        if (0 === $offset && !$this->fixAccounting('facturascli')) {
            return false;
        }

        if (0 === $offset && !$this->setModelCompany('FacturaCliente')) {
            return false;
        }

        if (0 === $offset && !$this->setModelStatusAll('FacturaCliente')) {
            return false;
        }

        if (0 === $offset) {
            /// needed dependency
            new LiquidacionComision();
        }

        $sql = "SELECT * FROM lineasfacturascli"
            . " WHERE idalbaran IS NOT null"
            . " AND idalbaran != '0'"
            . " ORDER BY idlinea ASC";

        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            $done = $this->newDocTransformation(
                'AlbaranCliente', $row['idalbaran'], $row['idlineaalbaran'], 'FacturaCliente', $row['idfactura'], $row['idlinea']
            );
            if (!$done) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    protected function fixAccounting($tableName)
    {
        if (!$this->dataBase->tableExists($tableName)) {
            return true;
        }

        $sql = "UPDATE " . $tableName . " SET idasiento = null WHERE idasiento IS NOT null"
            . " AND idasiento NOT IN (SELECT idasiento FROM asientos);"
            . "UPDATE " . $tableName . " SET idasientop = null WHERE idasientop IS NOT null"
            . " AND idasientop NOT IN (SELECT idasiento FROM asientos);";
        return $this->dataBase->exec($sql);
    }
}
