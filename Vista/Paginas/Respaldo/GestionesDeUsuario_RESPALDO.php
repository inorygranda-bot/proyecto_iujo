<?php
if (!isset($_SESSION['usuario'])) { header("location: ../../../Controlador/Autenticacion/inicioSesion.php"); exit(); }
$conexion = new mysqli("localhost", "root", "", "nombre_de_tu_bd");

// Acción para cambiar estado (Habilitar/Deshabilitar)
if (isset($_GET['cambiar']) && $_SESSION['rol'] == 'admin') {
    $id_u = $_GET['id_u'];
    $st = $_GET['st'];
    $conexion->query("UPDATE usuarios SET estado_acceso = $st WHERE id_usuario = $id_u");
    header("location: index.php?p=gestion");
}
?>
    <a href="index.php?p=inicio">Volver al Inicio</a>

    <h3>Registro de Empleados</h3>
    <form method="POST">
        <input type="text" name="n" placeholder="Nombres" required>
        <input type="text" name="a" placeholder="Apellidos" required>
        <input type="text" name="c" placeholder="Cédula" required>
        <input type="text" name="r" placeholder="RIF" required>
        <input type="text" name="d" placeholder="Departamento" required>
        <input type="text" name="ca" placeholder="Cargo" required>
        <button type="submit" name="add_e">Guardar</button>
    </form>

    <?php
    if(isset($_POST['add_e'])){
        $conexion->query("INSERT INTO empleados (nombres, apellidos, ci, rif, departamento, cargo) 
        VALUES ('{$_POST['n']}','{$_POST['a']}','{$_POST['c']}','{$_POST['r']}','{$_POST['d']}','{$_POST['ca']}')");
    }
    ?>

    <h3>Lista de Empleados Registrados</h3>
    <table border="1">
        <tr>
            <th>Nombre</th><th>Cédula</th><th>Cargo</th>
            <?php if($_SESSION['rol'] == 'admin') echo "<th>Acción</th>"; ?>
        </tr>
        <?php
        $res = $conexion->query("SELECT * FROM empleados");
        while($e = $res->fetch_object()){
            echo "<tr>
                <td>$e->nombres $e->apellidos</td>
                <td>$e->ci</td>
                <td>$e->cargo</td>";
            if($_SESSION['rol'] == 'admin'){
                echo "<td><a href='index.php?p=gestion&user_for=$e->id_empleado'>Asignar Usuario</a></td>";
            }
            echo "</tr>";
        }
        ?>
    </table>

    <?php if(isset($_GET['user_for']) && $_SESSION['rol'] == 'admin'): ?>
        <h3>Formulario: Crear Usuario</h3>
        <form method="POST">
            <input type="hidden" name="id_emp" value="<?php echo $_GET['user_for']; ?>">
            <input type="text" name="un" placeholder="Usuario" required><br>
            <input type="password" name="up" placeholder="Clave" required><br>
            <select name="ur">
                <option value="analista">Analista</option>
                <option value="admin">Administrador</option>
            </select><br>
            <button type="submit" name="save_u">Crear Acceso</button>
        </form>
    <?php endif; ?>

    <?php
    if(isset($_POST['save_u'])){
        $conexion->query("INSERT INTO usuarios (id_empleado, usuario, password, rol, estado_acceso) 
        VALUES ({$_POST['id_emp']}, '{$_POST['un']}', '{$_POST['up']}', '{$_POST['ur']}', 1)");
    }
    ?>

    <?php if($_SESSION['rol'] == 'admin'): ?>
        <h3>Lista de Usuarios y Estatus</h3>
        <table border="1">
            <tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acción</th></tr>
            <?php
            $res_u = $conexion->query("SELECT * FROM usuarios");
            while($u = $res_u->fetch_object()){
                $est = ($u->estado_acceso == 1) ? "Activo" : "Inactivo";
                $accion = ($u->estado_acceso == 1) ? 
                    "<a href='index.php?p=gestion&cambiar=1&id_u=$u->id_usuario&st=0'>Deshabilitar</a>" : 
                    "<a href='index.php?p=gestion&cambiar=1&id_u=$u->id_usuario&st=1'>Habilitar</a>";
                echo "<tr>
                    <td>$u->usuario</td>
                    <td>$u->rol</td>
                    <td>$est</td>
                    <td>$accion</td>
                </tr>";
            }
            ?>
        </table>
    <?php endif; ?>
