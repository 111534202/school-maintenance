<?php

namespace App\Http\Controllers;

/**
 * 所有 Controller 的共同父類別（abstract = 只能被繼承，不能單獨使用）。
 * 目前沒有需要全部 Controller 共用的程式，所以是空的；
 * 如果之後有「每支 Controller 都想用的方法」，寫在這裡，其他 Controller 就都能直接呼叫。
 */
abstract class Controller
{
    //
}
