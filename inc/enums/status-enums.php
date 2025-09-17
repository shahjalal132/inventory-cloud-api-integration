<?php

namespace BOILERPLATE\Inc\Enums;

enum Status_Enums: string {
    case PENDING = 'PENDING';
    case READY = 'READY';
    case IGNORED = 'IGNORED';
    case FAILED = 'FAILED';
    case COMPLETED = 'COMPLETED';
}