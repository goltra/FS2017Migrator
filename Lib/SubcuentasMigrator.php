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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Description of SubcuentasMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class SubcuentasMigrator extends InicioMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        $sql = "SELECT * FROM co_subcuentas ORDER BY idsubcuenta ASC";
        $rows = $this->dataBase->selectLimit($sql, FS_ITEM_LIMIT, $offset);
        foreach ($rows as $row) {
            if (!$this->newSubcuenta($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * 
     * @param Cuenta $cuenta
     * @param string $codcuenta
     * @param string $codejercicio
     *
     * @return bool
     */
    private function fixMissingCuenta(&$cuenta, $codcuenta, $codejercicio)
    {
        $parentCuenta = new Cuenta();
        $where = [
            new DataBaseWhere('codcuenta', substr($codcuenta, 1)),
            new DataBaseWhere('codejercicio', $codejercicio),
        ];
        if (!$parentCuenta->loadFromCode('', $where)) {
            return false;
        }

        $cuenta->codcuenta = $codcuenta;
        $cuenta->codejercicio = $codejercicio;
        $cuenta->descripcion = $codcuenta;
        $cuenta->parent_codcuenta = $parentCuenta->codcuenta;
        $cuenta->parent_idcuenta = $parentCuenta->idcuenta;
        return $cuenta->save();
    }

    /**
     * 
     * @param array $data
     *
     * @return bool
     */
    private function newSubcuenta($data)
    {
        $subcuenta = new Subcuenta();
        $where = [
            new DataBaseWhere('codejercicio', $data['codejercicio']),
            new DataBaseWhere('codsubcuenta', $data['codsubcuenta']),
        ];
        if ($subcuenta->loadFromCode('', $where)) {
            return true;
        }

        $cuenta = new Cuenta();
        $where2 = [
            new DataBaseWhere('codcuenta', $data['codcuenta']),
            new DataBaseWhere('codejercicio', $data['codejercicio']),
        ];
        if (!$cuenta->loadFromCode('', $where2) && !$this->fixMissingCuenta($cuenta, $data['codcuenta'], $data['codejercicio'])) {
            $this->miniLog->warning('account-missing');
            return false;
        }

        $subcuenta->codcuenta = $cuenta->codcuenta;
        $subcuenta->codejercicio = $data['codejercicio'];
        $subcuenta->codimpuesto = empty($data['codimpuesto']) ? null : $this->fixImpuesto($data['codimpuesto']);
        $subcuenta->codsubcuenta = $data['codsubcuenta'];
        $subcuenta->debe = $data['debe'];
        $subcuenta->descripcion = $data['descripcion'];
        $subcuenta->haber = $data['haber'];
        $subcuenta->idcuenta = $cuenta->primaryColumnValue();
        $subcuenta->idsubcuenta = $data['idsubcuenta'];
        $subcuenta->saldo = $data['saldo'];
        return $subcuenta->save();
    }
}
