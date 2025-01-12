<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\PagoCliente;
use FacturaScripts\Dinamic\Model\ReciboCliente;

/**
 * Description of RecibosClienteMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class RecibosClienteMigrator extends MigratorBase
{

    private function fixRecibos()
    {
        $sql = "DELETE FROM reciboscli WHERE codcliente IS NULL;"
            . "DELETE FROM reciboscli WHERE codcliente NOT IN (SELECT codcliente FROM clientes);"
            . "DELETE FROM reciboscli WHERE idfactura NOT IN (SELECT idfactura FROM facturascli)";
        $this->dataBase->exec($sql);
    }

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset && !$this->dataBase->tableExists('reciboscli')) {
            return true;
        }

        if (0 === $offset) {
            $this->fixRecibos();
        }

        $sql = 'SELECT * FROM reciboscli ORDER BY idrecibo ASC';
        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            if (false === $this->newReceipt($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * @param ReciboCliente $receipt
     *
     * @return bool
     */
    protected function newPayment($receipt): bool
    {
        $sql = 'SELECT * FROM pagosdevolcli WHERE idrecibo = '
            . $this->dataBase->var2str($receipt->idrecibo) . ' ORDER BY idpagodevol ASC';
        foreach ($this->dataBase->select($sql) as $row) {
            $newPayment = new PagoCliente($row);
            $newPayment->codpago = $receipt->codpago;
            $newPayment->disableAccountingGeneration(true);
            $newPayment->importe = $row['tipo'] == 'Pago' ? $receipt->importe : 0 - $receipt->importe;

            if (false === $newPayment->getAccountingEntry()->exists()) {
                $newPayment->idasiento = null;
            }

            if (false === $newPayment->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $row
     *
     * @return bool
     */
    protected function newReceipt($row): bool
    {
        $newReceipt = new ReciboCliente($row);
        $newReceipt->disablePaymentGeneration(true);
        $newReceipt->idempresa = $this->toolBox()->appSettings()->get('default', 'idempresa');
        $newReceipt->fechapago = date('d-m-Y', strtotime($row['fechap']));
        $newReceipt->vencimiento = date('d-m-Y', strtotime($row['fechav']));
        $newReceipt->pagado = $row['estado'] === 'Pagado';
        if ($newReceipt->exists()) {
            return true;
        }

        return $newReceipt->save() && $this->newPayment($newReceipt);
    }
}
