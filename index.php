<?php

/**
 * Novare Brindes — Redirecionador raiz para Hostinger.
 *
 * Este arquivo existe apenas para que a Hostinger reconheça a estrutura
 * do projeto. Toda requisição é encaminhada para public/index.php,
 * que é o front controller real da aplicação.
 */

// Carrega o front controller dentro de /public
require __DIR__ . '/public/index.php';
