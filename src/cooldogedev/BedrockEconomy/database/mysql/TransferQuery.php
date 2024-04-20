<?php

/**
 * MIT License
 *
 * Copyright (c) 2021-2024 cooldogedev
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @auto-license
 */

declare(strict_types=1);

namespace cooldogedev\BedrockEconomy\database\mysql;

use cooldogedev\BedrockEconomy\database\exception\RecordNotFoundException;
use cooldogedev\BedrockEconomy\database\exception\InsufficientFundsException;
use cooldogedev\BedrockEconomy\database\helper\AccountHolder;
use cooldogedev\BedrockEconomy\database\helper\ReferenceHolder;
use cooldogedev\BedrockEconomy\database\helper\TableHolder;
use cooldogedev\BedrockEconomy\libs\_eb725acc5712ec6d\cooldogedev\libSQL\query\MySQLQuery;
use mysqli;

final class TransferQuery extends MySQLQuery
{
    use AccountHolder;
    use ReferenceHolder;
    use TableHolder;

    public function __construct(
        private readonly int $amount,
        private readonly int $decimals,

        private readonly string $targetUsername,
        private readonly string $targetXuid,
    ) {}

    /**
     * @throws RecordNotFoundException
     * @throws InsufficientFundsException
     */
    public function onRun(mysqli $connection): void
    {
        $amount = $this->amount . "." . $this->decimals;
        $connection->begin_transaction();

        // check if the source account exists
        $sourceQuery = $connection->prepare("SELECT * FROM " . $this->table . " WHERE xuid = ? OR username = ?");
        $sourceQuery->bind_param("ss", $this->getRef($this->xuid), $this->getRef($this->username));
        $sourceQuery->execute();

        $sourceResult = $sourceQuery->get_result();

        if ($sourceResult->num_rows === 0) {
            $connection->rollback();
            throw new RecordNotFoundException(
                _message: "Account not found for xuid " . $this->xuid . " or username " . $this->username
            );
        }

        // check if the target account exists
        $targetQuery = $connection->prepare("SELECT * FROM " . $this->table . " WHERE xuid = ? OR username = ?");
        $targetQuery->bind_param("ss", $this->getRef($this->targetXuid), $this->getRef($this->targetUsername));
        $targetQuery->execute();

        $targetResult = $targetQuery->get_result();

        if ($targetResult->num_rows === 0) {
            $connection->rollback();
            throw new RecordNotFoundException(
                _message: "Account not found for xuid " . $this->targetXuid . " or username " . $this->targetUsername
            );
        }

        // subtract the money from the source account
        $sourceUpdateQuery = $connection->prepare("UPDATE " . $this->table . " SET amount = amount - ? WHERE (xuid = ? OR username = ?) AND amount >= ?");
        $sourceUpdateQuery->bind_param("issi", $amount, $this->getRef($this->xuid), $this->getRef($this->username), $amount);
        $sourceUpdateQuery->execute();

        if ($sourceUpdateQuery->affected_rows === 0) {
            $connection->rollback();
            throw new InsufficientFundsException(
                _message: "Insufficient funds for xuid " . $this->xuid . " or username " . $this->username
            );
        }

        // add the money to the target account
        $targetUpdateQuery = $connection->prepare("UPDATE " . $this->table . " SET amount = amount + ? WHERE xuid = ? OR username = ?");
        $targetUpdateQuery->bind_param("iss", $amount, $this->getRef($this->targetXuid), $this->getRef($this->targetUsername));
        $targetUpdateQuery->execute();

        if ($targetUpdateQuery->affected_rows === 0) {
            $connection->rollback();
            throw new RecordNotFoundException(
                _message: "Account not found for xuid " . $this->targetXuid . " or username " . $this->targetUsername
            );
        }

        $connection->commit();

        $targetResult->free();
        $sourceResult->free();

        $targetQuery->close();
        $sourceQuery->close();
        $targetUpdateQuery->close();
        $sourceUpdateQuery->close();

        $this->setResult($connection->affected_rows > 0 );
    }
}