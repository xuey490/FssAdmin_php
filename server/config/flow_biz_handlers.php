<?php

declare(strict_types=1);

/**
 * 流程业务钩子注册：按 fixed_form_key 匹配 Handler。
 * 审批内核不变；Handler 只处理账务副作用。
 */
return [
    'handlers' => [
        \App\Services\Flow\Handlers\AnnualLeaveFlowHandler::class,
        \App\Services\Flow\Handlers\CompensatoryLeaveFlowHandler::class,
        \App\Services\Flow\Handlers\PurchaseRequestFlowHandler::class,
        \App\Services\Flow\Handlers\DocumentFlowHandler::class,
        \App\Services\Flow\Handlers\CarFlowHandler::class,
    ],
];
