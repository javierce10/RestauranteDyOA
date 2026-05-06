<?php
session_start();
include('includes/conexion.php'); 

if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'caja'){
    header('Location: login.php');
    exit();
}

// ─── MODO JSON para el polling en tiempo real ───
if(isset($_GET['json'])){
    header('Content-Type: application/json');
    $res = $conn->query("
        SELECT p.*, MAX(v.metodo_pago) metodo_pago
        FROM pedidos p
        LEFT JOIN ventas v ON p.id = v.pedido_id
        GROUP BY p.id
        ORDER BY p.fecha_hora DESC
    ");
    $data = [];
    while($r = $res->fetch_assoc()) $data[] = $r;
    echo json_encode($data);
    exit();
}

// ─── PAGAR PEDIDO ───
if(isset($_POST['pagar_pedido'])){
    $pedido_id   = $_POST['pedido_id'];
    $metodo_pago = $_POST['metodo_pago'];

    $stmt = $conn->prepare("SELECT total FROM pedidos WHERE id=?");
    $stmt->bind_param("i", $pedido_id);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($pedido){
        $stmt = $conn->prepare("INSERT INTO ventas (pedido_id,total,metodo_pago,fecha)
        SELECT ?,?,?,NOW()
        WHERE NOT EXISTS (SELECT 1 FROM ventas WHERE pedido_id=?)");
        $stmt->bind_param("idsi",$pedido_id,$pedido['total'],$metodo_pago,$pedido_id);
        $stmt->execute();
        $stmt->close();

        $conn->query("UPDATE pedidos SET estado='pagado' WHERE id=$pedido_id");
        $mensaje = "Pedido #$pedido_id cobrado.";
    }
}

// ─── PEDIDOS ───
$res = $conn->query("
    SELECT p.*, MAX(v.metodo_pago) metodo_pago
    FROM pedidos p
    LEFT JOIN ventas v ON p.id=v.pedido_id
    GROUP BY p.id
    ORDER BY p.fecha_hora DESC
");
$pedidos = [];
while($r = $res->fetch_assoc()) $pedidos[] = $r;
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel de Caja</title>

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.container { max-width:1400px; margin:0 auto; }

/* HEADER */
.header {
    background: white;
    padding: 25px 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header h2 { color:#667eea; font-size:26px; }

@keyframes pulso {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:0.4; transform:scale(0.7); }
}

.btn-nav {
    padding: 12px 20px;
    border-radius: 10px;
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    color: white;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: 0.3s;
}
.btn-nav:hover { transform: translateY(-2px); }

/* SECCIONES */
.section {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    margin-bottom: 25px;
}
.section h3 {
    color: #667eea;
    margin-bottom: 20px;
    font-size: 22px;
    border-bottom: 3px solid #667eea;
    padding-bottom: 10px;
}

/* TABLA */
table { width:100%; border-collapse:collapse; }
th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    text-align: left;
}
td { padding:12px 15px; border-bottom:1px solid #e0e0e0; }
tr:hover { background-color:#f8f9fa; }

.fila-pendiente { background-color: rgba(255,243,205,0.4); }
.fila-pagado    { background-color: rgba(212,237,218,0.4); }

/* BADGES */
.badge { padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
.badge-pendiente { background-color:#ffc107; }
.badge-pagado    { background-color:#28a745; color:white; }

/* BOTONES */
.btn-action {
    padding: 8px 15px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 13px;
    margin: 2px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
}
.btn-pagar  { background:linear-gradient(135deg,#56ab2f,#a8e063); color:white; }
.btn-detalle{ background:linear-gradient(135deg,#4facfe,#00f2fe); color:white; }
.btn-ticket { background:linear-gradient(135deg,#f093fb,#f5576c); color:white; }

/* MODAL BASE */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 999;
}
.modal-content {
    background: white;
    margin: 8% auto;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    overflow: hidden;
}
.modal-header {
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: white;
    padding: 20px;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-body { padding:25px; text-align:center; }

.metodo-pago { display:flex; gap:15px; margin-bottom:25px; }
.metodo-btn {
    flex: 1;
    padding: 25px;
    border: 2px solid #ddd;
    border-radius: 15px;
    cursor: pointer;
    transition: 0.3s;
    background: #f8f9fa;
    font-size: 30px;
}
.metodo-btn span { display:block; font-size:14px; margin-top:8px; }
.metodo-btn.selected { border-color:#667eea; background:#eef2ff; }

.modal-actions { display:flex; gap:10px; margin-top:20px; }
.btn-cancelar {
    flex:1; padding:12px; border:none; border-radius:10px;
    background:linear-gradient(135deg,#ff416c,#ff4b2b);
    color:white; font-weight:600; cursor:pointer;
}
.btn-confirmar {
    flex:1; padding:12px; border:none; border-radius:10px;
    background:#ccc; color:white; font-weight:600; cursor:pointer;
}
.btn-confirmar.activo {
    background: linear-gradient(135deg,#56ab2f,#a8e063);
}

/* NOTIFICACIÓN */
.notif {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    background: linear-gradient(135deg,#56ab2f,#a8e063);
    color: white;
    padding: 16px 22px;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    font-weight: 600;
    font-size: 15px;
    animation: aparecer 0.3s ease;
}

@keyframes aparecer {
    from { opacity:0; transform:translateY(-15px) scale(0.95); }
    to   { opacity:1; transform:translateY(0)     scale(1);    }
}
</style>
</head>

<body>
<div class="container">

<!-- HEADER -->
<div class="header">
    <h2>🎯 Panel de Caja</h2>
    <div class="live-badge">
        <div class="live-dot"></div>
    </div>
    <a href="logout.php" class="btn-nav">Salir</a>
</div>

<?php if(isset($mensaje)): ?>
<div style="background:#d4edda;padding:10px;margin-bottom:15px;border-radius:8px;">
    <?= $mensaje ?>
</div>
<?php endif; ?>

<!-- TABLA PEDIDOS -->
<div class="section">
    <h3>📋 Pedidos</h3>
    <table>
        <thead>
            <tr>
                <th>Mesa</th><th>Mesero</th><th>ID</th>
                <th>Total</th><th>Estado</th><th>Método</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tablaPedidos">
            <?php foreach($pedidos as $p): ?>
            <tr class="<?= $p['estado']=='pendiente' ? 'fila-pendiente' : 'fila-pagado' ?>">
                <td><?= $p['mesa'] ?></td>
                <td>
                    <span style="background:#f093fb;color:white;padding:4px 10px;border-radius:12px;font-size:12px;">
                        👤 <?= $p['mesero_nombre'] ?>
                    </span>
                </td>
                <td>#<?= $p['id'] ?></td>
                <td>$<?= number_format($p['total'],2) ?></td>
                <td>
                    <span class="badge <?= $p['estado']=='pendiente'?'badge-pendiente':'badge-pagado' ?>">
                        <?= $p['estado'] ?>
                    </span>
                </td>
                <td><?= $p['metodo_pago'] ? ucfirst($p['metodo_pago']) : '<span style="color:#999;">Sin definir</span>' ?></td>
                <td>
                    <?php if($p['estado']=='pendiente'): ?>
                        <button class="btn-action btn-pagar" onclick="abrirModalPago(<?= $p['id'] ?>,<?= $p['total'] ?>)">
                            💰 Cobrar
                        </button>
                    <?php endif; ?>
                    <a href="admin_detalle.php?pedido_id=<?= $p['id'] ?>" class="btn-action btn-detalle">Detalle</a>
                    <?php if($p['estado']=='pagado'): ?>
                        <a href="ticket.php?pedido_id=<?= $p['id'] ?>" target="_blank" class="btn-action btn-ticket">Ticket</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div><!-- /container -->

<!-- MODAL PAGO -->
<div id="modalPago" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <span>💰 Cobrar Pedido</span>
      <button onclick="cerrar()" style="background:rgba(255,255,255,0.2);border:none;color:white;font-size:20px;cursor:pointer;border-radius:50%;width:32px;height:32px;">✕</button>
    </div>
    <div class="modal-body">

      <!-- RESUMEN -->
      <div style="background:#f0f4ff;border-radius:12px;padding:15px;margin-bottom:20px;text-align:left;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#555;">Pedido:</span>
          <strong>#<span id="pedidoTexto"></span></strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:#555;">Total a cobrar:</span>
          <strong style="color:#667eea;font-size:22px;">$<span id="totalTexto"></span></strong>
        </div>
      </div>

      <form method="POST" onsubmit="return validarPago()">
        <input type="hidden" name="pedido_id" id="pedidoId">
        <input type="hidden" name="metodo_pago" id="metodoPago">

        <p style="font-weight:600;color:#444;margin-bottom:10px;text-align:left;">Método de pago:</p>
        <div class="metodo-pago">
          <div class="metodo-btn" onclick="seleccionar('efectivo',this)">💵<span>Efectivo</span></div>
          <div class="metodo-btn" onclick="seleccionar('tarjeta',this)">💳<span>Tarjeta</span></div>
        </div>

        <!-- SECCIÓN EFECTIVO -->
        <div id="seccionEfectivo" style="display:none;">
          <p style="font-weight:600;color:#444;margin-bottom:8px;text-align:left;">Cantidad recibida:</p>
          <div id="botonesRapidos" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;"></div>
          <input type="number" id="montoRecibido" min="0" step="0.01"
            placeholder="Ingresa el monto recibido"
            oninput="calcularCambio()"
            style="width:100%;padding:14px;border:2px solid #ddd;border-radius:10px;font-size:18px;text-align:center;outline:none;transition:0.3s;"
            onfocus="this.style.borderColor='#667eea'"
            onblur="this.style.borderColor='#ddd'">
          <div id="cajaResultado" style="margin-top:15px;display:none;">
            <div id="cajaCambio" style="border-radius:12px;padding:18px;text-align:center;">
              <p style="font-size:13px;margin-bottom:4px;" id="labelCambio"></p>
              <p style="font-size:28px;font-weight:700;" id="montoCambio"></p>
            </div>
          </div>
        </div>

        <!-- SECCIÓN TARJETA -->
        <div id="seccionTarjeta" style="display:none;background:#f0fff4;border-radius:12px;padding:15px;margin-bottom:10px;text-align:center;">
          <p style="color:#28a745;font-size:14px;">✅ Pago con tarjeta — no requiere cambio</p>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-cancelar" onclick="cerrar()">Cancelar</button>
          <button type="submit" name="pagar_pedido" id="confirmar" class="btn-confirmar" disabled>
            Confirmar Pago
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ════════════════════════════════
//   MODAL DE PAGO
// ════════════════════════════════
let totalActual = 0;

function abrirModalPago(id, total) {
    totalActual = parseFloat(total);
    document.getElementById('modalPago').style.display = 'block';
    document.getElementById('pedidoId').value          = id;
    document.getElementById('pedidoTexto').innerText   = id;
    document.getElementById('totalTexto').innerText    = totalActual.toFixed(2);

    document.getElementById('confirmar').disabled = true;
    document.getElementById('confirmar').classList.remove('activo');
    document.getElementById('montoRecibido').value = '';
    document.getElementById('cajaResultado').style.display  = 'none';
    document.getElementById('seccionEfectivo').style.display = 'none';
    document.getElementById('seccionTarjeta').style.display  = 'none';
    document.querySelectorAll('.metodo-btn').forEach(b => b.classList.remove('selected'));

    generarBotonesRapidos(totalActual);
}

function generarBotonesRapidos(total) {
    const base   = [50, 100, 200, 500];
    const exacto = Math.ceil(total);
    const unicos = [...new Set([exacto, ...base.filter(b => b >= total)])].sort((a,b) => a-b).slice(0,5);

    const cont = document.getElementById('botonesRapidos');
    cont.innerHTML = '';
    unicos.forEach(m => {
        const btn = document.createElement('button');
        btn.type      = 'button';
        btn.innerText = m === exacto ? `$${m} (exacto)` : `$${m}`;
        btn.style.cssText = `flex:1;min-width:80px;padding:10px;border:2px solid #667eea;
            border-radius:10px;background:white;color:#667eea;
            font-weight:600;cursor:pointer;font-size:13px;transition:0.2s;`;
        btn.onmouseover = () => { btn.style.background='#667eea'; btn.style.color='white'; };
        btn.onmouseout  = () => { btn.style.background='white';   btn.style.color='#667eea'; };
        btn.onclick = () => {
            document.getElementById('montoRecibido').value = m;
            calcularCambio();
        };
        cont.appendChild(btn);
    });
}

function seleccionar(metodo, el) {
    document.getElementById('metodoPago').value = metodo;
    document.querySelectorAll('.metodo-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');

    if(metodo === 'efectivo'){
        document.getElementById('seccionEfectivo').style.display = 'block';
        document.getElementById('seccionTarjeta').style.display  = 'none';
        document.getElementById('confirmar').disabled = true;
        document.getElementById('confirmar').classList.remove('activo');
        calcularCambio();
    } else {
        document.getElementById('seccionEfectivo').style.display = 'none';
        document.getElementById('seccionTarjeta').style.display  = 'block';
        document.getElementById('cajaResultado').style.display   = 'none';
        document.getElementById('confirmar').disabled = false;
        document.getElementById('confirmar').classList.add('activo');
    }
}

function calcularCambio() {
    const recibido   = parseFloat(document.getElementById('montoRecibido').value) || 0;
    const caja       = document.getElementById('cajaResultado');
    const cajaCambio = document.getElementById('cajaCambio');
    const confirmar  = document.getElementById('confirmar');

    if(recibido <= 0){
        caja.style.display = 'none';
        confirmar.disabled = true;
        confirmar.classList.remove('activo');
        return;
    }

    caja.style.display = 'block';
    const cambio = recibido - totalActual;

    if(cambio < 0){
        cajaCambio.style.background = '#fff0f0';
        cajaCambio.style.border     = '2px solid #ff4b2b';
        document.getElementById('labelCambio').style.color = '#c00';
        document.getElementById('montoCambio').style.color = '#c00';
        document.getElementById('labelCambio').innerText   = '⚠️ Monto insuficiente — falta:';
        document.getElementById('montoCambio').innerText   = `$${Math.abs(cambio).toFixed(2)}`;
        confirmar.disabled = true;
        confirmar.classList.remove('activo');
    } else if(cambio === 0){
        cajaCambio.style.background = '#e8f5e9';
        cajaCambio.style.border     = '2px solid #56ab2f';
        document.getElementById('labelCambio').style.color = '#2e7d32';
        document.getElementById('montoCambio').style.color = '#2e7d32';
        document.getElementById('labelCambio').innerText   = '✅ Monto exacto';
        document.getElementById('montoCambio').innerText   = '$0.00';
        confirmar.disabled = false;
        confirmar.classList.add('activo');
    } else {
        cajaCambio.style.background = '#e3f2fd';
        cajaCambio.style.border     = '2px solid #4facfe';
        document.getElementById('labelCambio').style.color = '#1565c0';
        document.getElementById('montoCambio').style.color = '#1565c0';
        document.getElementById('labelCambio').innerText   = '💰 Cambio a entregar:';
        document.getElementById('montoCambio').innerText   = `$${cambio.toFixed(2)}`;
        confirmar.disabled = false;
        confirmar.classList.add('activo');
    }
}

function validarPago() {
    const metodo = document.getElementById('metodoPago').value;
    if(metodo === 'efectivo'){
        const recibido = parseFloat(document.getElementById('montoRecibido').value) || 0;
        if(recibido < totalActual){ alert('El monto recibido es menor al total.'); return false; }
    }
    return true;
}

function cerrar(){ document.getElementById('modalPago').style.display = 'none'; }

window.onclick = e => {
    if(e.target == document.getElementById('modalPago')) cerrar();
};

// ════════════════════════════════
//   POLLING — TIEMPO REAL
// ════════════════════════════════

// IDs que ya están en pantalla al cargar la página
let idsConocidos = new Set(<?= json_encode(array_column($pedidos, 'id')) ?>);

function renderTabla(pedidos) {
    const tbody = document.getElementById('tablaPedidos');
    tbody.innerHTML = '';

    pedidos.forEach(p => {
        const pendiente  = p.estado === 'pendiente';
        const metodoTxt  = p.metodo_pago
            ? p.metodo_pago.charAt(0).toUpperCase() + p.metodo_pago.slice(1)
            : '<span style="color:#999;">Sin definir</span>';

        tbody.innerHTML += `
        <tr class="${pendiente ? 'fila-pendiente' : 'fila-pagado'}">
            <td>${p.mesa}</td>
            <td>
                <span style="background:#f093fb;color:white;padding:4px 10px;
                    border-radius:12px;font-size:12px;">
                    👤 ${p.mesero_nombre}
                </span>
            </td>
            <td>#${p.id}</td>
            <td>$${parseFloat(p.total).toFixed(2)}</td>
            <td>
                <span class="badge ${pendiente ? 'badge-pendiente' : 'badge-pagado'}">
                    ${p.estado}
                </span>
            </td>
            <td>${metodoTxt}</td>
            <td>
                ${pendiente
                    ? `<button class="btn-action btn-pagar"
                          onclick="abrirModalPago(${p.id},${p.total})">
                          💰 Cobrar
                       </button>`
                    : ''}
                <a href="admin_detalle.php?pedido_id=${p.id}"
                    class="btn-action btn-detalle">Detalle</a>
                ${!pendiente
                    ? `<a href="ticket.php?pedido_id=${p.id}" target="_blank"
                          class="btn-action btn-ticket">Ticket</a>`
                    : ''}
            </td>
        </tr>`;
    });
}

function mostrarNotificacion(nuevos) {
    const nombres = nuevos.map(p => `Mesa ${p.mesa}`).join(', ');
    const div     = document.createElement('div');
    div.className = 'notif';
    div.innerHTML = `🆕 Nuevo pedido: ${nombres}`;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}

function reproducirSonido() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [523, 659, 784].forEach((freq, i) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            const t = ctx.currentTime + i * 0.18;
            gain.gain.setValueAtTime(0.3, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.3);
            osc.start(t);
            osc.stop(t + 0.3);
        });
    } catch(e) {}
}

function polling() {
    fetch('caja.php?json=1')
        .then(res => res.json())
        .then(pedidos => {
            // Detectar pedidos nuevos
            const nuevos = pedidos.filter(p => !idsConocidos.has(p.id));

            if(nuevos.length > 0){
                nuevos.forEach(p => idsConocidos.add(p.id));
                mostrarNotificacion(nuevos);
                reproducirSonido();
            }

            // Siempre re-renderiza para reflejar cambios de estado
            renderTabla(pedidos);
        })
        .catch(() => {}); // Silencioso si falla
}

// Consulta cada 5 segundos
setInterval(polling, 5000);
</script>
</body>
</html>