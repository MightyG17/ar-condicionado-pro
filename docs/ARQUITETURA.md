# Arquitetura do Sistema

## 📊 Diagrama de Tabelas

```
projetos (1:N) ambientes
                   |
                   +--(1:N)-- paredes --(1:N)-- itens_parede
                   +--(1:N)-- ganho_solar
                   +--(1:N)-- cargas_internas
                   +--(1:N)-- renovacao_ar
                   +--(1:N)-- infiltracao_ambiente
                   +--(1:1)-- resultados_calculo     (Etapa 5)
                   +--(1:N)-- carga_horaria          (Etapa A)

Independentes:
  irradiacao_cresesb (27 capitais)   (Etapa C)
  equipamentos (40 modelos)          (Etapa D)
```

## 🔄 Fluxo de Dados

1. Cadastro do projeto -> cidade, UF
2. Cadastro do ambiente -> dimensões, uso
3. Cadastro de paredes/aberturas
4. Dimensionamento (Etapa 5) -> resultados_calculo
5. Cálculo horário (Etapa A) -> carga_horaria (usa CRESESB)
6. Dashboard (Etapa B) -> compara fixo vs horário
7. Sugestão de equipamentos (Etapa D) -> filtra >= carga x 1.10

## 🧮 Motor de Cálculo (calculo_horario.php)

### Funções principais
1. posicao_solar(lat, dia, hora) -> elevação + azimute
2. irradiacao_vertical(irr, elev, azim, orient) -> W/m2
3. irradiacao_normal_tipica(mes, hora) -> fallback teórico
4. buscar_irradiacao_cresesb(conn, cidade, uf) -> dados reais
5. irradiacao_cresesb_hora(json, orient, mes, hora) -> W/m2
6. calcular_perfil_horario(conn, amb_id) -> 156 pontos

## 🗂️ Estrutura de Diretórios

```
ar_condicionado_pro/
  index.php
  ambiente_horario.php
  README.md / CHANGELOG.md / LICENSE / CONTRIBUTING.md
  config/config.php
  includes/calculo_horario.php
  paginas/
    residencial_projetos.php
    residencial_ambiente.php
    ambiente_dados.php
    ambiente_paredes.php
    ambiente_dimensionamento.php
    ambiente_resultado.php
    ambiente_memoria_calculo.php
    projeto_dashboard.php
    equipamentos_sugeridos.php
    cidades_cresesb.php
  docs/
    ARQUITETURA.md
    INSTALACAO.md
    API.md
    FORMULAS.md
  banco/schema.sql
```
