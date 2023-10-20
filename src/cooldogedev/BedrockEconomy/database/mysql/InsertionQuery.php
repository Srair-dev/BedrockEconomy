<?php

/**
 * MIT License
 *
 * Copyright (c) 2021-2023 cooldogedev
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

use cooldogedev\BedrockEconomy\database\exception\RecordAlreadyExistsException;
use cooldogedev\BedrockEconomy\database\helper\AccountHolder;
use cooldogedev\BedrockEconomy\database\helper\TableHolder;
use cooldogedev\libSQL\query\MySQLQuery;
use mysqli;

final class InsertionQuery extends MySQLQuery
{
    use AccountHolder;
    use TableHolder;

    public function __construct(protected int $amount, protected int $decimals) {}

    /**
     * @throws RecordAlreadyExistsException
     */
    public function onRun(mysqli $connection): void
    {
        // start transaction
        $connection->begin_transaction();

        // check if account exists
        $checkQuery = $connection->prepare("SELECT * FROM " . $this->table . " WHERE xuid = ? OR username = ?");
        $checkQuery->bind_param("ss", $this->xuid, $this->username);
        $checkQuery->execute();

        $checkResult = $checkQuery->get_result();
        $checkQuery->close();

        if ($checkResult->num_rows > 0) {
            $connection->rollback();
            throw new RecordAlreadyExistsException(
                _message: "Account already exists for xuid " . $this->xuid . " or username " . $this->username
            );
        }

        // insert account
        $insertionQuery = $connection->prepare("INSERT IF NOT EXISTS INTO " . $this->table . " (xuid, username, amount, decimals) VALUES (?, ?, ?, ?)");
        $insertionQuery->bind_param("ssii", $this->xuid, $this->username, $this->amount, $this->decimals);
        $insertionQuery->execute();

        $insertionResult = $insertionQuery->get_result();
        $insertionQuery->close();

        if ($insertionResult->num_rows === 0) {
            $connection->rollback();
            throw new RecordAlreadyExistsException(
                _message: "Account already exists for xuid " . $this->xuid . " or username " . $this->username
            );
        }

        $connection->commit();

        $checkResult->free();
        $insertionResult->free();

        $this->setResult($connection->affected_rows > 0);
    }
}
