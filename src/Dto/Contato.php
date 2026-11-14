<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Enum\Genero;
use MonteiroTi\Sei\Enum\Natureza;
use MonteiroTi\Sei\Enum\OperacaoContato;

/**
 * Estrutura Contato (listarContatos e atualizarContatos).
 *
 * Em atualizarContatos, cidade, estado, país e cargo são identificados apenas pelos IDs
 * (IdCidade, IdEstado, IdPais, IdCargo) e da pessoa jurídica associada só são processados
 * IdContatoAssociado e SinEnderecoAssociado.
 */
final readonly class Contato implements FromSoap, ToSoap
{
    public function __construct(
        public ?OperacaoContato $operacao = null,
        public ?string $idContato = null,
        public ?string $idTipoContato = null,
        public ?string $nomeTipoContato = null,
        public ?string $sigla = null,
        public ?string $nome = null,
        public ?string $nomeSocial = null,
        public ?Natureza $natureza = null,
        public ?string $idContatoAssociado = null,
        public ?string $nomeContatoAssociado = null,
        public ?bool $enderecoAssociado = null,
        public ?string $cnpjAssociado = null,
        public ?string $endereco = null,
        public ?string $complemento = null,
        public ?string $bairro = null,
        public ?string $idCidade = null,
        public ?string $nomeCidade = null,
        public ?string $idEstado = null,
        public ?string $siglaEstado = null,
        public ?string $idPais = null,
        public ?string $nomePais = null,
        public ?string $cep = null,
        public ?Genero $genero = null,
        public ?string $idCargo = null,
        public ?string $expressaoCargo = null,
        public ?string $expressaoTratamento = null,
        public ?string $expressaoVocativo = null,
        public ?string $cpf = null,
        public ?string $cnpj = null,
        public ?string $rg = null,
        public ?string $orgaoExpedidor = null,
        public ?string $matricula = null,
        public ?string $matriculaOab = null,
        public ?string $telefoneFixo = null,
        public ?string $telefoneCelular = null,
        public ?DateTimeInterface $dataNascimento = null,
        public ?string $email = null,
        public ?string $sitioInternet = null,
        public ?string $observacao = null,
        public ?string $numeroPassaporte = null,
        public ?string $idPaisPassaporte = null,
        public ?string $nomePaisPassaporte = null,
        public ?bool $ativo = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            operacao: $data->enum('StaOperacao', OperacaoContato::class),
            idContato: $data->id('IdContato'),
            idTipoContato: $data->id('IdTipoContato'),
            nomeTipoContato: $data->string('NomeTipoContato'),
            sigla: $data->string('Sigla'),
            nome: $data->string('Nome'),
            nomeSocial: $data->string('NomeSocial'),
            natureza: $data->enum('StaNatureza', Natureza::class),
            idContatoAssociado: $data->id('IdContatoAssociado'),
            nomeContatoAssociado: $data->string('NomeContatoAssociado'),
            enderecoAssociado: $data->bool('SinEnderecoAssociado'),
            cnpjAssociado: $data->string('CnpjAssociado'),
            endereco: $data->string('Endereco'),
            complemento: $data->string('Complemento'),
            bairro: $data->string('Bairro'),
            idCidade: $data->id('IdCidade'),
            nomeCidade: $data->string('NomeCidade'),
            idEstado: $data->id('IdEstado'),
            siglaEstado: $data->string('SiglaEstado'),
            idPais: $data->id('IdPais'),
            nomePais: $data->string('NomePais'),
            cep: $data->string('Cep'),
            genero: $data->enum('StaGenero', Genero::class),
            idCargo: $data->id('IdCargo'),
            expressaoCargo: $data->string('ExpressaoCargo'),
            expressaoTratamento: $data->string('ExpressaoTratamento'),
            expressaoVocativo: $data->string('ExpressaoVocativo'),
            cpf: $data->string('Cpf'),
            cnpj: $data->string('Cnpj'),
            rg: $data->string('Rg'),
            orgaoExpedidor: $data->string('OrgaoExpedidor'),
            matricula: $data->string('Matricula'),
            matriculaOab: $data->string('MatriculaOab'),
            telefoneFixo: $data->string('TelefoneFixo'),
            telefoneCelular: $data->string('TelefoneCelular'),
            dataNascimento: $data->date('DataNascimento'),
            email: $data->string('Email'),
            sitioInternet: $data->string('SitioInternet'),
            observacao: $data->string('Observacao'),
            numeroPassaporte: $data->string('NumeroPassaporte'),
            idPaisPassaporte: $data->id('IdPaisPassaporte'),
            nomePaisPassaporte: $data->string('NomePaisPassaporte'),
            ativo: $data->bool('SinAtivo'),
        );
    }

    public function toSoap(): array
    {
        return [
            'StaOperacao' => $this->operacao?->value,
            'IdContato' => $this->idContato,
            'IdTipoContato' => $this->idTipoContato,
            'NomeTipoContato' => $this->nomeTipoContato,
            'Sigla' => $this->sigla,
            'Nome' => $this->nome,
            'NomeSocial' => $this->nomeSocial,
            'StaNatureza' => $this->natureza?->value,
            'IdContatoAssociado' => $this->idContatoAssociado,
            'NomeContatoAssociado' => $this->nomeContatoAssociado,
            'SinEnderecoAssociado' => Values::sn($this->enderecoAssociado),
            'CnpjAssociado' => $this->cnpjAssociado,
            'Endereco' => $this->endereco,
            'Complemento' => $this->complemento,
            'Bairro' => $this->bairro,
            'IdCidade' => $this->idCidade,
            'NomeCidade' => $this->nomeCidade,
            'IdEstado' => $this->idEstado,
            'SiglaEstado' => $this->siglaEstado,
            'IdPais' => $this->idPais,
            'NomePais' => $this->nomePais,
            'Cep' => $this->cep,
            'StaGenero' => $this->genero?->value,
            'IdCargo' => $this->idCargo,
            'ExpressaoCargo' => $this->expressaoCargo,
            'ExpressaoTratamento' => $this->expressaoTratamento,
            'ExpressaoVocativo' => $this->expressaoVocativo,
            'Cpf' => $this->cpf,
            'Cnpj' => $this->cnpj,
            'Rg' => $this->rg,
            'OrgaoExpedidor' => $this->orgaoExpedidor,
            'Matricula' => $this->matricula,
            'MatriculaOab' => $this->matriculaOab,
            'TelefoneFixo' => $this->telefoneFixo,
            'TelefoneCelular' => $this->telefoneCelular,
            'DataNascimento' => Values::date($this->dataNascimento),
            'Email' => $this->email,
            'SitioInternet' => $this->sitioInternet,
            'Observacao' => $this->observacao,
            'NumeroPassaporte' => $this->numeroPassaporte,
            'IdPaisPassaporte' => $this->idPaisPassaporte,
            'NomePaisPassaporte' => $this->nomePaisPassaporte,
            'SinAtivo' => Values::sn($this->ativo),
        ];
    }

    /** Cópia com outra operação (ex.: para desativar um contato lido de listarContatos). */
    public function comOperacao(OperacaoContato $operacao): self
    {
        $args = get_object_vars($this);
        $args['operacao'] = $operacao;

        return new self(...$args);
    }
}
