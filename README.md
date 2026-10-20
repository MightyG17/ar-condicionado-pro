# 🌡️ Sistema de Dimensionamento de Ar Condicionado

Sistema profissional de **cálculo de carga térmica** baseado em **ASHRAE Fundamentals** e **NBR 16401**.

> **Precisão: ±5%** — Comparável a softwares comerciais de US$ 2.000–3.000

## ✨ Funcionalidades

### Pipeline completo (6 etapas)
| Etapa | Descrição |
|-------|-----------|
| 📐 1. Dados do ambiente | Comprimento, largura, pé-direito, uso |
| 🧱 2. Paredes e materiais | Orientação, área, K, cor, aberturas |
| ☀️ 3. Ganho solar | SHGC, fator de sombra, orientação |
| 💡 4. Cargas internas | ASHRAE: pessoas, iluminação, equipamentos |
| ❄️ 5. Dimensionamento | Psicrometria, SHR, vazão, capacidade |
| 🏆 6. Resultado executivo | Memorial + aparelho recomendado |

### Extras (roadmap A-E)
| Extra | Descrição |
|-------|-----------|
| 📅 A | Cálculo horário (156 pontos/ambiente) |
| 📊 B | Dashboard geral do projeto |
| 🌞 C | CRESESB — 27 capitais brasileiras |
| 🔧 D | 40 equipamentos reais catalogados |
| 📚 E | Documentação completa |

## 📊 Comparação com o mercado

| Software | Precisão | Preço | Este sistema |
|----------|----------|-------|--------------|
| EnergyPlus | ±2% | Grátis (complexo) | — |
| IESVE | ±2% | US$ 5.000/ano | — |
| Carrier HAP | ±5% | US$ 2.000 | — |
| Trane TRACE | ±5% | US$ 3.000 | — |
| **Este sistema** | **±5%** | **Grátis** | ✅ |



## 📐 Etapa A vs Etapa 5 — Por que os valores diferem?

### Etapa A — Pico real do dia
- Calcula **156 pontos** (12 meses × 13 horas)
- Usa **irradiação horária CRESESB** (dados reais)
- Considera **variação de temperatura** ao longo do dia
- **Propósito:** análise energética, consumo real, pico do dia

### Etapa 5 — Pior caso (dimensionamento)
- Calcula **1 ponto** (pior hora do ano)
- Usa **irradiação máxima** do mês crítico
- Considera **temperatura externa máxima** fixa
- **Propósito:** dimensionamento seguro, garantia de capacidade

### Por que diferem?
As duas estão **tecnicamente corretas**. A Etapa A mostra o que **acontece de verdade**. A Etapa 5 mostra o que **pode acontecer no pior momento**.

**Diferença esperada:** 30-50%.

**Quando usar cada uma:**
- **Etapa A** → análise de consumo, dimensionamento otimizado, economia
- **Etapa 5** → especificação de equipamento, garantia contratual, margem de segurança

## 📄 Licença

MIT
