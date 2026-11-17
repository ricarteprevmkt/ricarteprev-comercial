<?php

namespace App\Actions\C2;

use RuntimeException;

/** Cota de consultas da rodada esgotada: o restante fica para a próxima. */
final class BudgetExhausted extends RuntimeException {}
