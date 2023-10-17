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

namespace cooldogedev\BedrockEconomy\database\migration\economyapi;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\BedrockEconomy;
use cooldogedev\BedrockEconomy\database\migration\IMigration;
use Generator;
use SOFe\AwaitGenerator\Await;

final class Migration implements IMigration
{
    public function getName(): string
    {
        return "EconomyAPI";
    }

    public function getMin(): string
    {
        return "0.0.1";
    }

    public function getMax(): string
    {
        return "0.0.1";
    }

    public function run(string $mode): bool
    {
        $economyAPI = BedrockEconomy::getInstance()->getServer()->getPluginManager()->getPlugin("EconomyAPI");

        if ($economyAPI === null) {
            return false;
        }

        Await::f2c(
            function () use ($economyAPI): Generator {
                foreach ($economyAPI->getAllMoney() as $username => $money) {
                    $money = explode(".", (string)$money);
                    $amount = $money[0] ?? 0;
                    $decimals = $money[1] ?? 0;

                    $success = yield from BedrockEconomyAPI::ASYNC()->insert($username, $username, $amount, $decimals);

                    if (!$success) {
                        BedrockEconomy::getInstance()->getLogger()->warning("Failed to migrate data for player " . $username . ". (" . $this->getName() . ")");
                    }
                }
            }
        );

        return true;
    }
}