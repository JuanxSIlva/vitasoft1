# VitaSoft conectado a MySQL

## Instalación en XAMPP
1. Copia la carpeta `VitaSoft` dentro de `C:\xampp\htdocs\`.
2. Abre phpMyAdmin.
3. Importa `database.sql`.
4. Verifica que MySQL esté iniciado.
5. Abre `http://localhost/VitaSoft/`.
6. Usuario inicial: `admin`
7. Contraseña inicial: `123`

## Qué ya queda conectado
- Login contra `usuarios`.
- Productos: crear, editar y eliminar.
- Categorías: crear, editar y eliminar.
- Laboratorios: crear, editar y eliminar.
- Entradas: registra la entrada y aumenta el stock.
- Salidas: registra la salida y descuenta el stock.
- Inventario: consulta el stock real.
- Dashboard: consulta datos reales.
- Configuración: guarda cambios en la base de datos.

## Importante sobre eliminaciones
Las categorías, laboratorios y productos tienen relaciones históricas. MySQL impedirá borrar un registro que tenga datos dependientes para evitar dejar el inventario inconsistente. La pantalla muestra el mensaje correspondiente.
