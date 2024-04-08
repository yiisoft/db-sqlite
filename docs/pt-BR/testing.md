# Testes

## Ações do Github

Todos os nossos pacotes possuem ações do github por padrão, então você pode testar sua [contribuição](https://github.com/yiisoft/db-sqlite/blob/master/.github/CONTRIBUTING.md) na nuvem.

> Observação: recomendamos a solicitação pull no modo rascunho até que todos os testes sejam aprovados.

## Teste de unidade

O pacote é testado com [PHPUnit](https://phpunit.de/).

```shell
vendor/bin/phpunit
```

### Teste de mutação

Os testes do pacote são verificados com a estrutura de mutação [Infection](https://infection.github.io/) e com
[plugin de análise estática de infecção](https://github.com/Roave/infection-static-analysis-plugin). Para executá-lo:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

## Análise estática

O código é analisado estaticamente com [Psalm](https://psalm.dev/). Para executar a análise estática:

```shell
./vendor/bin/psalm
```

## Reitor

Use [Rector](https://github.com/rectorphp/rector) para fazer a base de código seguir algumas regras específicas ou
use a versão mais recente ou qualquer versão específica do PHP:

```shell
./vendor/bin/rector
```

## Composer requer verificador

Este pacote usa [composer-require-checker](https://github.com/maglnet/ComposerRequireChecker) para verificar se todas as dependências estão definidas corretamente em `composer.json`.

Para executar o verificador, execute o seguinte comando:

```shell
./vendor/bin/composer-require-checker
```
