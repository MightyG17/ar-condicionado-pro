# API Interna

## calculo_horario.php

### posicao_solar(latitude, dia_ano, hora_solar)
Retorna array com elevacao e azimute em graus.

### irradiacao_vertical(irr_normal, elevacao, azimute_sol, orientacao)
Retorna irradiação incidente em W/m2.

### irradiacao_normal_tipica(mes, hora)
Modelo teórico (fallback). Retorna W/m2.

### buscar_irradiacao_cresesb(conn, cidade, uf)
Consulta a tabela irradiacao_cresesb. Retorna array ou null.

### irradiacao_cresesb_hora(perfil_json, orientacao, mes, hora)
Converte perfil mensal em irradiação horária.

### calcular_perfil_horario(conn, ambiente_id)
Calcula 156 pontos e grava em carga_horaria.

Retorno:
array(
  sucesso => true,
  pico => array(carga => 2146, mes => 2, hora => 15),
  area => 25.0,
  janelas => 2,
  paredes => 1
)

## Uso típico

```php
require_once config/config.php;
require_once includes/calculo_horario.php;

r = calcular_perfil_horario(conn, 9);
if (r[sucesso]) {
    echo Pico: . r[pico][carga] . W em . r[pico][mes] . / . r[pico][hora] . h;
}
```
