# Digitalizador Catastral

Editor geométrico catastral autónomo y embebible, orientado a operaciones de alta precisión sobre parcelas.

## Principios

- Web Component + JS SDK framework-agnostic para integración en sistemas existentes.
- API geométrica autónoma desacoplada de los esquemas de los sistemas anfitriones.
- PostgreSQL/PostGIS como fuente de verdad geométrica.
- Sin sistema propio de usuarios, roles ni permisos: el sistema anfitrión controla el acceso.
- Operaciones de dominio explícitas: modificación, desglose, unión y remodelado de límites compartidos.
- Auditoría técnica, control de concurrencia, validación y recuperación de borradores.

El desarrollo activo se realizará fuera de `main` mediante ramas de feature.
