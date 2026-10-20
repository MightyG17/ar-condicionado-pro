# Fórmulas Utilizadas

## 1. Posição Solar

### Declinação (Cooper)
δ = 23.45 × sin(360 × (284 + n) / 365)

### Elevação
sin(β) = sin(φ)·sin(δ) + cos(φ)·cos(δ)·cos(ω)

### Azimute
cos(α) = [sin(δ) - sin(φ)·sin(β)] / [cos(φ)·cos(β)]

## 2. Irradiação Incidente

I_inc = I_normal × cos(β) × cos(Δazim)

## 3. Carga Solar

Q_solar = I_inc × A_vidro × SHGC × FS

## 4. Carga Interna (ASHRAE)

Q_pessoas = n × 130 W
Q_ilum    = P × fator_uso
Q_equip   = P × fator_demanda

## 5. Condução

Q_conducao = U × A × ΔT

## 6. Capacidade

BTU/h = Q_total(W) × 3.412
TR    = BTU/h ÷ 12.000

## 7. Sugestão de Equipamento

BTU_necessario = carga_btu × 1.10

## 8. CRESESB

I_pico = (I_diaria × 1000) / 5.5
fator_hora = sin(π × (hora - 6) / 12)
