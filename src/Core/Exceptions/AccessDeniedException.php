<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Exceptions;

/**
 * Sistema, serviço, chave, operação, unidade ou tipo não liberados no SEI/SIP.
 */
class AccessDeniedException extends FaultException {}
