<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

/**
 * Prepara argumentos e mensagens para log (LGPD).
 *
 * - Credenciais (IdentificacaoServico, ChaveAcesso) são sempre trocadas por ***.
 * - Dados pessoais são mascarados, removidos ou mantidos conforme o modo.
 * - Textos longos (Base64) são resumidos.
 *
 * "Nome", "NomeSocial" e "Sigla" só são tratados como dados pessoais dentro de estruturas
 * de contato (Interessados, Remetente, Destinatarios, Contatos, Usuarios) e na raiz de
 * registrarOuvidoria, porque também nomeiam arquivos, campos e unidades.
 */
final class Redactor
{
    public const MODO_MASCARAR = 'mascarar';

    public const MODO_REMOVER = 'remover';

    public const MODO_MANTER = 'manter';

    public const SECRET_KEYS = ['IdentificacaoServico', 'ChaveAcesso'];

    /** Chaves com dado pessoal em qualquer contexto (comparação sem diferenciar maiúsculas). */
    public const PERSONAL_KEYS = [
        'Cpf', 'Cnpj', 'CnpjAssociado', 'Rg', 'OrgaoExpedidor', 'Email', 'De', 'Para', 'CCO',
        'Telefone', 'TelefoneFixo', 'TelefoneCelular', 'Endereco', 'Complemento', 'Bairro', 'Cep',
        'DataNascimento', 'NumeroPassaporte', 'Matricula', 'MatriculaOab', 'Mensagem',
    ];

    /** Chaves com dado pessoal apenas dentro de estruturas de contato. */
    public const CONTACT_KEYS = ['Nome', 'NomeSocial', 'Sigla', 'NomeContatoAssociado'];

    /** Estruturas cujos itens representam pessoas. */
    public const CONTACT_CONTEXTS = ['Interessados', 'Remetente', 'Destinatarios', 'Contatos', 'Usuarios'];

    /** Operações cuja raiz descreve uma pessoa. */
    public const CONTACT_OPERATIONS = ['registrarOuvidoria'];

    public const MAX_LENGTH = 200;

    private const CPF_PATTERN = '/(?<![\d.\/-])(?:\d{3}\.\d{3}\.\d{3}-\d{2}|\d{11})(?![\d.\/-])/';

    private const CNPJ_PATTERN = '/(?<![\d.\/-])(?:\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{14})(?![\d.\/-])/';

    private const EMAIL_PATTERN = '/([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/';

    private static ?self $default = null;

    /** @var array<string, true> */
    private array $personal;

    /**
     * @param  string  $modo  mascarar | remover | manter
     * @param  list<string>  $chavesExtras  Chaves adicionais tratadas como dado pessoal
     */
    public function __construct(
        private readonly string $modo = self::MODO_MASCARAR,
        array $chavesExtras = [],
    ) {
        if (! in_array($modo, [self::MODO_MASCARAR, self::MODO_REMOVER, self::MODO_MANTER], true)) {
            throw new \InvalidArgumentException("Modo de dados pessoais inválido: {$modo}.");
        }

        $this->personal = array_fill_keys(array_map('strtolower', [...self::PERSONAL_KEYS, ...$chavesExtras]), true);
    }

    public static function default(): self
    {
        return self::$default ??= new self;
    }

    /**
     * Atalho estático com a configuração padrão.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public static function redact(array $arguments, ?string $operation = null): array
    {
        return self::default()->arguments($arguments, $operation);
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public function arguments(array $arguments, ?string $operation = null): array
    {
        return $this->walk($arguments, in_array($operation, self::CONTACT_OPERATIONS, true));
    }

    /** Mascara CPF, CNPJ e e-mail em texto livre (ex.: mensagens de erro do SEI). */
    public function text(string $text): string
    {
        if ($this->modo === self::MODO_MANTER) {
            return $text;
        }

        $text = (string) preg_replace_callback(self::CNPJ_PATTERN, fn (array $m) => self::maskCnpj($m[0]), $text);
        $text = (string) preg_replace_callback(self::CPF_PATTERN, fn (array $m) => self::maskCpf($m[0]), $text);

        return (string) preg_replace(self::EMAIL_PATTERN, '$1***@$2', $text);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function walk(array $values, bool $contact): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $name = is_string($key) ? $key : null;

            if ($name !== null && in_array($name, self::SECRET_KEYS, true)) {
                $out[$key] = $value === null ? null : '***';

                continue;
            }

            if ($name !== null && $value !== null && $this->modo !== self::MODO_MANTER && $this->isPersonal($name, $contact)) {
                if ($this->modo === self::MODO_REMOVER) {
                    continue;
                }
                $out[$key] = $this->mask($name, $value);

                continue;
            }

            $childContact = $name === null ? $contact : in_array($name, self::CONTACT_CONTEXTS, true);
            $out[$key] = $this->value($value, $childContact);
        }

        return $out;
    }

    private function value(mixed $value, bool $contact): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            return $this->walk($value, $contact);
        }
        if (is_string($value) && strlen($value) > self::MAX_LENGTH) {
            return sprintf('[%d bytes] %s…', strlen($value), substr($value, 0, 40));
        }

        return $value;
    }

    private function isPersonal(string $key, bool $contact): bool
    {
        if (isset($this->personal[strtolower($key)])) {
            return true;
        }

        return $contact && in_array($key, self::CONTACT_KEYS, true);
    }

    private function mask(string $key, mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return '***';
        }

        $value = (string) $value;

        return match (strtolower($key)) {
            'cpf' => self::maskCpf($value),
            'cnpj', 'cnpjassociado' => self::maskCnpj($value),
            'email', 'de', 'para', 'cco' => implode(';', array_map(
                fn (string $e) => (string) preg_replace(self::EMAIL_PATTERN, '$1***@$2', trim($e)),
                explode(';', $value),
            )),
            'nome', 'nomesocial', 'nomecontatoassociado' => self::initials($value),
            'mensagem' => sprintf('[%d caracteres]', mb_strlen($value)),
            default => '***',
        };
    }

    private static function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf) ?? '';

        return strlen($digits) === 11 ? '***.***.***-'.substr($digits, -2) : '***';
    }

    private static function maskCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? '';

        return strlen($digits) === 14 ? '**.***.***/****-'.substr($digits, -2) : '***';
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = array_map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)).'.', array_filter($parts, fn ($p) => $p !== ''));

        return $initials === [] ? '***' : implode(' ', $initials);
    }
}
