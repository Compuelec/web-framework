# Glosario contable — Contabilidad PyMe

Términos que aparecen en las pantallas, el plan de cuentas y los documentos
tributarios. Pensado para alguien que no es contador y que tiene que cargar
información en el sistema.

## Documentos tributarios

### Folio
**Número único del documento.** Es la palabra técnica/legal que usa el SII
(Servicio de Impuestos Internos de Chile). En lenguaje común sería "número de
factura" o "número de boleta".

Cada **tipo de documento mantiene su propia serie**. Por eso una factura
afecta puede tener folio `5001` y una boleta de honorarios del mismo día
tener folio `87` — son consecutivos dentro de su serie, no entre series.

Ejemplo en el sistema:
- Factura afecta N° **5001** (folio 5001 de la serie de facturas).
- Boleta de honorarios N° **88** (folio 88 de la serie del contador).

### Tipo de documento

Los que el sistema reconoce hoy:

| Tipo | Cuándo se usa | Lleva IVA |
|---|---|---|
| **factura_afecta** | Venta a una empresa con giro registrado | Sí (19%) |
| **factura_exenta** | Venta de servicios educacionales, médicos, etc. | No |
| **boleta** | Venta a un cliente final (persona, no empresa) | Sí (19%) |
| **nota_credito** | Anula o reduce una factura ya emitida | Reversa el IVA |
| **nota_debito** | Aumenta el monto de una factura ya emitida | Suma IVA |
| **boleta_honorarios** | Servicios profesionales (contador, abogado, etc.) | No (lleva retención del 10% en otro contexto, acá se trata como exento) |

### Razón social
Nombre legal de la empresa o persona. **No es el nombre comercial** — es el
nombre que aparece en el SII y en los documentos tributarios.

Ejemplo:
- Razón social: `Comercial Las Camelias S.A.`
- Nombre de fantasía: `Café Camelias` (este no se carga acá)

### RUT
Identificador tributario chileno. Formato: `XX.XXX.XXX-Y`. Sirve para
personas naturales y jurídicas. La `Y` final es el dígito verificador.

### Giro
Actividad económica declarada al SII. Aparece en cada factura. Ejemplo:
"Comercio al por menor", "Servicios informáticos", "Cafetería".

## Montos del documento

Cada comprobante (venta o compra) trae varios montos:

| Campo | Qué significa |
|---|---|
| **Neto** | Precio del bien o servicio sin IVA. |
| **IVA** | Impuesto al Valor Agregado, **19%** sobre el neto (en Chile). |
| **Exento** | Parte del documento que no paga IVA (servicios exentos, educación, salud). |
| **Total** | Neto + IVA + Exento. Es lo que el cliente realmente paga. |

Ejemplo: una factura por servicios de impresión por $850.000 + IVA:
- Neto: `850.000`
- IVA: `161.500` (19% de 850.000)
- Exento: `0`
- **Total: `1.011.500`**

Una boleta de honorarios del contador por $380.000:
- Neto: `0`
- IVA: `0`
- Exento: `380.000`
- **Total: `380.000`**

## Plan de cuentas

### Cuenta contable
Es una "casilla" del libro contable donde se anotan los movimientos de
dinero. Ejemplo: "Caja" (1.1.01), "Banco" (1.1.02), "Ventas afectas"
(4.1.01).

El código sigue el formato **C.G.NN.SS**:
- **C** = Clase (1=Activo, 2=Pasivo, 3=Patrimonio, 4=Ingreso, 5=Gasto, 6=Costo)
- **G** = Grupo dentro de la clase
- **NN** = Cuenta
- **SS** = Subcuenta (opcional)

### Naturaleza (deudora / acreedora)
Cada cuenta tiene una **naturaleza** que dice "cuándo sube su saldo":

- **Deudora**: sube con DÉBITO (cargo en el debe), baja con CRÉDITO.
  - Activo: Caja, Banco, Clientes, IVA Crédito Fiscal.
  - Gastos: Sueldos, Arriendos, Honorarios.
- **Acreedora**: sube con CRÉDITO (abono en el haber), baja con DÉBITO.
  - Pasivo: Proveedores, IVA Débito Fiscal.
  - Patrimonio: Capital.
  - Ingresos: Ventas afectas, Ventas exentas.

> Truco mnemotécnico: lo que **te deben** y los **gastos** son deudoras.
> Lo que **debés** y los **ingresos** son acreedoras.

## Asiento contable

### Asiento
Es **un movimiento contable** que registra una transacción. Cada asiento
tiene 2 o más líneas y la suma del DEBE debe ser igual a la suma del HABER
("doble partida").

Ejemplo: una venta a crédito por $1.011.500 IVA incluido genera este
asiento:

```
N° 7        2026-06-12      origen: venta
─────────────────────────────────────────────────────────
1.1.03  Clientes                  $ 1.011.500
4.1.01  Ventas afectas                              $ 850.000
2.1.02  IVA Débito Fiscal                           $ 161.500
─────────────────────────────────────────────────────────
                  Totales:        $ 1.011.500       $ 1.011.500   ✓ cuadra
```

### Debe / Haber
Las dos columnas del asiento. Convencionalmente:
- **Debe** (izquierda) = lo que entra a una cuenta deudora o sale de una
  acreedora.
- **Haber** (derecha) = lo que entra a una cuenta acreedora o sale de una
  deudora.

### Glosa
Descripción libre del asiento o de cada línea. Ejemplos:
- "Factura N° 5001 a Café del Centro"
- "Pago arriendo junio 2026"

### Origen
De dónde nace el asiento:
- **manual**: lo cargó el usuario directo en "Asientos contables".
- **venta**: generado automáticamente al apretar "Generar asiento" sobre
  una factura/boleta de venta.
- **compra**: igual para una factura recibida.
- **pago**: registra el pago efectivo de un proveedor (no implementado).
- **ajuste**: correcciones de fin de período (no implementado).

### Estado
- **borrador**: el asiento existe pero no afecta los saldos del balance.
- **validado**: el asiento se contabiliza.
- **anulado**: el asiento fue dado de baja.

> El sistema genera todos los asientos automáticos como `validado` por
> defecto.

## IVA — los dos lados

### IVA Débito Fiscal
El IVA que la empresa **cobra a sus clientes** y que después debe pagarle al
SII. Es un pasivo (lo que la empresa le debe al fisco). Sube cada venta
afecta.

### IVA Crédito Fiscal
El IVA que la empresa **paga al comprar** y que puede descontar del Débito.
Es un activo (lo que el fisco le debe a la empresa). Sube cada compra
afecta.

### IVA a pagar
**Débito Fiscal − Crédito Fiscal**. Esto es lo que la empresa le debe pagar
al SII el día 12 del mes siguiente (formulario 29).

Si el Crédito es mayor que el Débito (mes con muchas compras), no hay nada
que pagar y el remanente queda para descontar en el mes siguiente.

## Estado de un comprobante

| Estado | Significado |
|---|---|
| **Venta** | |
| emitido | Documento ya enviado al cliente. Es el estado normal. |
| anulado | Se emitió pero después se canceló (no se cobra). |
| **Compra** | |
| registrado | Se cargó en el sistema pero todavía no se paga. |
| pagado | Ya se efectuó el pago al proveedor. |
| anulado | El documento no se va a pagar (devolución, error, etc.). |

## Glosa de campos varios

| Campo | Explicación |
|---|---|
| **Archivo (PDF/imagen)** | Foto o PDF del documento original. Se guarda adjunto al comprobante para tener el respaldo digital. |
| **Categoría** (en compras) | Clasifica el gasto (arriendos, servicios básicos, materiales de oficina, etc.) para que el asiento sepa a qué cuenta de gasto cargarlo. |
| **Cliente** / **Proveedor** | El otro lado de la transacción. En el sistema aparece como un id numérico; en los libros de venta y compra el sistema muestra la razón social. |
