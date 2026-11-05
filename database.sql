CREATE DATABASE IF NOT EXISTS vitasoft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vitasoft;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS movimientos, detalle_salidas, salidas, detalle_entradas, entradas, lotes, productos, proveedores, notificaciones, recuperacion_password, configuracion, usuarios, laboratorios, categorias, roles;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE roles(
 id INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(50) NOT NULL UNIQUE,
 descripcion VARCHAR(255),
 estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB;

CREATE TABLE usuarios(
 id INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(120) NOT NULL,
 usuario VARCHAR(60) NOT NULL UNIQUE,
 correo VARCHAR(150) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 rol_id INT NOT NULL,
 estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
 fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ultimo_acceso DATETIME NULL,
 FOREIGN KEY(rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE categorias(
 id INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL UNIQUE,
 descripcion VARCHAR(255),
 estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
 fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE laboratorios(
 id INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(120) NOT NULL UNIQUE,
 descripcion VARCHAR(255),
 logo VARCHAR(255),
 pais VARCHAR(80),
 estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
 fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE proveedores(
 id INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL,
 nit VARCHAR(50),
 telefono VARCHAR(40),
 correo VARCHAR(150),
 direccion VARCHAR(255),
 estado ENUM('Activo','Inactivo') DEFAULT 'Activo',
 fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE productos(
 id INT AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(50) NOT NULL UNIQUE,
 nombre VARCHAR(180) NOT NULL,
 categoria_id INT NOT NULL,
 laboratorio_id INT NOT NULL,
 precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0,
 precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0,
 stock INT NOT NULL DEFAULT 0,
 stock_minimo INT NOT NULL DEFAULT 10,
 unidad_medida VARCHAR(40) DEFAULT 'Unidad',
 descripcion VARCHAR(255),
 estado ENUM('Activo','Inactivo') DEFAULT 'Activo',
 fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
 FOREIGN KEY(laboratorio_id) REFERENCES laboratorios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE lotes(
 id INT AUTO_INCREMENT PRIMARY KEY,
 producto_id INT NOT NULL,
 numero_lote VARCHAR(80) NOT NULL,
 fecha_fabricacion DATE NULL,
 fecha_vencimiento DATE NULL,
 cantidad INT NOT NULL DEFAULT 0,
 FOREIGN KEY(producto_id) REFERENCES productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE entradas(
 id INT AUTO_INCREMENT PRIMARY KEY,
 proveedor_id INT NOT NULL,
 usuario_id INT NOT NULL,
 fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
 numero_factura VARCHAR(80),
 observacion VARCHAR(255),
 FOREIGN KEY(proveedor_id) REFERENCES proveedores(id),
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE detalle_entradas(
 id INT AUTO_INCREMENT PRIMARY KEY,
 entrada_id INT NOT NULL,
 producto_id INT NOT NULL,
 lote_id INT NULL,
 cantidad INT NOT NULL,
 precio_compra DECIMAL(12,2) NOT NULL,
 FOREIGN KEY(entrada_id) REFERENCES entradas(id) ON DELETE CASCADE,
 FOREIGN KEY(producto_id) REFERENCES productos(id),
 FOREIGN KEY(lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE salidas(
 id INT AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT NOT NULL,
 fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
 tipo_salida ENUM('Venta','Vencimiento','Dañado','Ajuste','Otro') DEFAULT 'Venta',
 observacion VARCHAR(255),
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE detalle_salidas(
 id INT AUTO_INCREMENT PRIMARY KEY,
 salida_id INT NOT NULL,
 producto_id INT NOT NULL,
 lote_id INT NULL,
 cantidad INT NOT NULL,
 FOREIGN KEY(salida_id) REFERENCES salidas(id) ON DELETE CASCADE,
 FOREIGN KEY(producto_id) REFERENCES productos(id),
 FOREIGN KEY(lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE movimientos(
 id INT AUTO_INCREMENT PRIMARY KEY,
 producto_id INT NOT NULL,
 usuario_id INT NOT NULL,
 tipo ENUM('Entrada','Salida','Ajuste') NOT NULL,
 cantidad INT NOT NULL,
 fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
 observacion VARCHAR(255),
 FOREIGN KEY(producto_id) REFERENCES productos(id),
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE recuperacion_password(
 id INT AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT NOT NULL,
 token VARCHAR(255) NOT NULL,
 fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
 fecha_expiracion DATETIME NOT NULL,
 utilizado TINYINT(1) DEFAULT 0,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notificaciones(
 id INT AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT NULL,
 tipo ENUM('Stock Bajo','Próximo a Vencer','Vencido','Información') NOT NULL,
 titulo VARCHAR(150) NOT NULL,
 mensaje VARCHAR(255) NOT NULL,
 leida TINYINT(1) DEFAULT 0,
 fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE configuracion(
 id INT PRIMARY KEY,
 nombre_drogueria VARCHAR(150),
 nit VARCHAR(50),
 telefono VARCHAR(40),
 correo VARCHAR(150),
 direccion VARCHAR(255),
 ciudad VARCHAR(80),
 departamento VARCHAR(80),
 logo VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO roles(nombre,descripcion) VALUES
('Administrador','Acceso total'),('Empleado','Operación del inventario');

-- Contraseña inicial: 123
INSERT INTO usuarios(nombre,usuario,correo,password,rol_id)
VALUES('Administrador','admin','admin@vitasoft.com',
'$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy',1);

INSERT INTO categorias(nombre,descripcion) VALUES
('Analgésicos','Medicamentos para dolor'),
('Antibióticos','Medicamentos antibacterianos'),
('Antialérgicos','Tratamiento de alergias'),
('Gastrointestinal','Sistema digestivo'),
('Vitaminas','Vitaminas y suplementos'),
('Corticoides','Medicamentos corticoides'),
('Respiratorio','Sistema respiratorio'),
('Cardiovascular','Sistema cardiovascular'),
('Antidiabéticos','Control de diabetes');

INSERT INTO laboratorios(nombre,pais) VALUES
('MK','Colombia'),('Genfar','Colombia'),('Bayer','Alemania'),
('Pfizer','Estados Unidos'),('Tecnoquímicas','Colombia'),
('Abbott','Estados Unidos'),('Sanofi','Francia'),('GSK','Reino Unido'),
('Roche','Suiza'),('Novartis','Suiza');

INSERT INTO proveedores(nombre,nit,telefono,correo,direccion) VALUES
('Proveedor General','900000000-1','3000000000','proveedor@vitasoft.com','Teruel, Huila');

INSERT INTO configuracion(id,nombre_drogueria,telefono,correo,direccion,ciudad,departamento)
VALUES(1,'VitaSoft Droguería','3000000000','admin@vitasoft.com','Centro','Teruel','Huila');

INSERT INTO productos(codigo,nombre,categoria_id,laboratorio_id,precio_compra,precio_venta,stock,stock_minimo) VALUES
('P0001','Paracetamol 500mg',1,2,120,250,45,10),
('P0002','Ibuprofeno 400mg',1,1,150,300,8,10),
('P0003','Amoxicilina 500mg',2,2,300,550,25,10),
('P0004','Loratadina 10mg',3,1,200,350,3,10),
('P0005','Omeprazol 20mg',4,5,350,600,12,10),
('P0006','Vitamina C 500mg',5,2,100,200,60,10),
('P0007','Dexametasona 4mg',6,3,250,450,18,10),
('P0008','Salbutamol Inhalador',7,8,280,500,6,10),
('P0009','Losartán 50mg',8,1,220,400,0,10),
('P0010','Metformina 850mg',9,2,180,320,15,10);
