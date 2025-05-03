<?php
// src/Exceptions.php

namespace App\Exceptions;

/**
 * Exceções específicas para Service Layer SAP B1
 */
class ServiceLayerException      extends \Exception {}
class AuthenticationException    extends ServiceLayerException {}
class UnauthorizedException      extends ServiceLayerException {}
class ForbiddenException         extends ServiceLayerException {}
class NotFoundException          extends ServiceLayerException {}
class AlreadyCanceledException   extends ServiceLayerException {}
class BusinessException          extends ServiceLayerException {}
