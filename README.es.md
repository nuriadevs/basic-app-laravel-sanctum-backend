# Aplicación Básica Laravel 13 Sanctum (Backend)

📘 Disponible también en [Inglés](./README.md)

> **Actualización:** la aplicación se ha actualizado a Laravel 13 (requiere PHP 8.3 o superior). La versión anterior con Laravel 12 sigue disponible en la rama [`main`](https://github.com/nuriadevs/basic-app-laravel-12-sanctum-backend/tree/main).

Esta aplicación ha sido desarrollada con Laravel, ofrece operaciones CRUD y un sistema básico de autenticación y registro.

## Entorno 🛠️

- PHP 8.4 o superior
- [Laravel 13](https://laravel.com/docs/13.x)
- [Laravel Sanctum](https://laravel.com/docs/13.x/sanctum#main-content)
- API
- Tests
- CRUD
- MySQL
- Postman o Bruno
- Visual Studio Code

## Instalación ⚙️

1.  Clona la rama de Laravel 13 en tu máquina local: `git clone -b upgrade-laravel-13 https://github.com/nuriadevs/basic-app-laravel-12-sanctum-backend`.
2.  Instala las dependencias de PHP con `composer install`.
3.  Copia el archivo `.env.example` a `.env` con `cp .env.example .env`.
4.  Genera una clave de aplicación con `php artisan key:generate`.
5.  Configura tu base de datos en el archivo `.env` (`DB_DATABASE=shop`, usuario y contraseña) y usa `SESSION_DRIVER=file`.
6.  Crea la base de datos y los datos de prueba importando el script SQL (no uses `php artisan migrate`): `mysql -u root -p < database/shop_completo.sql`
7.  Inicia el servidor de desarrollo con `php artisan serve`.
8.  Accede a la aplicación en tu navegador web en [http://localhost:8000](http://localhost:8000).

> ⚠️ El script ejecuta `DROP DATABASE IF EXISTS shop`, así que elimina cualquier base de datos `shop` que ya tengas.

### Usuarios de prueba

Rol

Email

Contraseña

Admin

[laura@example.com](mailto:laura@example.com)

Admin1234!

Cliente

[carlos@example.com](mailto:carlos@example.com), [ana@example.com](mailto:ana@example.com), [david@example.com](mailto:david@example.com), [javier@example.com](mailto:javier@example.com), [lucia@example.com](mailto:lucia@example.com)

Customer1234!

Inactivo

[marta@example.com](mailto:marta@example.com)

Customer1234!

## Endpoints de la API

| Endpoint            | **Métodos** | Rutas                  |
| ------------------- | ----------- | ---------------------- |
| **Autenticación**   |             |                        |
| Login               | POST        | api/auth/login         |
| Register            | POST        | api/auth/register      |
| Logout              | POST        | api/auth/logout        |
| Usuario autenticado | GET         | api/user               |
| **Usuarios**        |             |                        |
| Index               | GET         | api/users              |
| Show                | GET         | api/users/{user}       |
| Store               | POST        | api/users              |
| Update              | PATCH       | api/users/{user}       |
| Destroy             | DELETE      | api/users/{user}       |
| **Productos**       |             |                        |
| Index               | GET         | api/products           |
| Show                | GET         | api/products/{product} |
| Store               | POST        | api/products           |
| Update              | PATCH       | api/products/{product} |
| Destroy             | DELETE      | api/products/{product} |
| **Pedidos**         |             |                        |
| Index               | GET         | api/orders             |
| Show                | GET         | api/orders/{order}     |
| Store               | POST        | api/orders             |
| Update              | PATCH       | api/orders/{order}     |
| Destroy             | DELETE      | api/orders/{order}     |

## Variables de Entorno

Visita `.env.example` para crear los entornos.

## Demo Postman :man_astronaut:

<img src="./media/1.png" alt="postman" width="600" />

## Esquema de la Base de Datos

<img src="./media/bbdd.png" alt="database-schema" width="600" />

## Enlaces

- [Laravel](https://laravel.com/docs/13.x/releases)
- [Bruno](https://www.usebruno.com/)
- [Postman](https://www.postman.com/)
- [PHP](https://www.php.net/)

## Resumen

- No olvides crear tu propio archivo `.env` para las variables.
- Los datos utilizados en la api son todos ficticios, son utilizados para probar.
- Los datos de prueba se cargan con el script `shop_completo.sql`.
- La columna `email_verified_at` está preparada, pero la verificación de email aún no está implementada.
- Este proyecto está en construcción... puede ser mejorado.

## Gracias 🍀

Muchas gracias por leer este proyecto.
