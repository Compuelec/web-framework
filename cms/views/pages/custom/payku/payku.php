<?php

/**
 * Payku Plugin Configuration Page
 */

// Calculate CMS directory path
// Current file: cms/views/pages/custom/payku/payku.php
// Target: cms/controllers/payku.controller.php
// Need to go up 4 levels: payku -> custom -> pages -> views -> cms
$cmsDir = dirname(__DIR__, 4);

// Load Payku controller
require_once $cmsDir . '/controllers/payku.controller.php';

$paykuController = new PaykuController();
$paykuController->managePayku();

$config = PaykuController::getConfig();

?>

<div class="container-fluid p-4">
    <div class="card rounded shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">
                <i class="fas fa-credit-card mr-2"></i>
                Configuración de Payku
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" id="paykuConfigForm">
                <input type="hidden" name="save_payku_config" value="1">
                
                <!-- Enable/Disable Plugin -->
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="enabled" name="enabled" <?php echo $config['enabled'] ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="enabled">
                            <strong>Activar Plugin Payku</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">Activa o desactiva el plugin de pagos Payku</small>
                </div>
                
                <hr>
                
                <!-- Platform Selection -->
                <div class="form-group">
                    <label for="platform_id">
                        <strong>Plataforma</strong>
                    </label>
                    <select class="form-control" id="platform_id" name="platform_id" required>
                        <option value="TEST" <?php echo $config['platform_id'] == 'TEST' ? 'selected' : ''; ?>>Plataforma de Prueba (Sandbox)</option>
                        <option value="PROD" <?php echo $config['platform_id'] == 'PROD' ? 'selected' : ''; ?>>Plataforma de Producción</option>
                    </select>
                    <small class="form-text text-muted">
                        <strong>⚠️ Importante:</strong> Cada plataforma requiere su propio token.
                        <ul class="mt-2 mb-0" style="padding-left: 20px;">
                            <li><strong>Sandbox:</strong> Obtén tu token en <a href="https://des.payku.cl" target="_blank">https://des.payku.cl</a></li>
                            <li><strong>Producción:</strong> Obtén tu token en <a href="https://app.payku.cl" target="_blank">https://app.payku.cl</a></li>
                        </ul>
                    </small>
                </div>
                
                <!-- Payment Mode -->
                <div class="form-group">
                    <label for="pagoDirecto">
                        <strong>Modo de Acceso</strong>
                    </label>
                    <select class="form-control" id="pagoDirecto" name="pagoDirecto" required>
                        <option value="1" <?php echo $config['pagoDirecto'] == '1' ? 'selected' : ''; ?>>Ingresar directamente a Webpay Plus</option>
                        <option value="99" <?php echo $config['pagoDirecto'] == '99' ? 'selected' : ''; ?>>Mostrar pasarela de pagos</option>
                    </select>
                    <small class="form-text text-muted">Define cómo se accederá al sistema de pagos</small>
                </div>
                
                <!-- Public Token -->
                <div class="form-group">
                    <label for="token_publico">
                        <strong>Token Público</strong> <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="token_publico" name="token_publico" 
                           value="<?php echo htmlspecialchars($config['token_publico']); ?>" 
                           placeholder="Ingrese su token público de Payku" required>
                    <small class="form-text text-muted">
                        Token público corresponde al identificador único de seguridad de su cuenta Payku.
                        <br><strong>⚠️ Asegúrate de usar el token correcto según la plataforma seleccionada:</strong>
                        <ul class="mt-1 mb-0" style="padding-left: 20px;">
                            <li>Si seleccionaste <strong>Sandbox</strong>, usa un token de <a href="https://des.payku.cl" target="_blank">des.payku.cl</a></li>
                            <li>Si seleccionaste <strong>Producción</strong>, usa un token de <a href="https://app.payku.cl" target="_blank">app.payku.cl</a></li>
                        </ul>
                    </small>
                </div>
                
                <!-- Marketplace Token -->
                <div class="form-group">
                    <label for="marketplace">
                        <strong>Token Marketplace</strong> <span class="text-muted">(Opcional)</span>
                    </label>
                    <input type="text" class="form-control" id="marketplace" name="marketplace" 
                           value="<?php echo htmlspecialchars($config['marketplace']); ?>" 
                           placeholder="Ingrese token Marketplace (solo para usuarios marketplace)">
                    <small class="form-text text-muted">Este campo es requerido únicamente para usuarios marketplace. De lo contrario, dejar en blanco.</small>
                </div>
                
                <!-- Increment Percentage -->
                <div class="form-group">
                    <label for="incremento">
                        <strong>Incremento (en %)</strong>
                    </label>
                    <input type="number" class="form-control" id="incremento" name="incremento" 
                           value="<?php echo htmlspecialchars($config['incremento']); ?>" 
                           min="0" max="100" step="0.01" placeholder="0">
                    <small class="form-text text-muted">Si no desea aumentar el valor total de la orden, colocar 0</small>
                </div>
                
                <!-- Payment Status -->
                <div class="form-group">
                    <label for="estadoPago">
                        <strong>Estado del Pago Exitoso</strong>
                    </label>
                    <input type="text" class="form-control" id="estadoPago" name="estadoPago" 
                           value="<?php echo htmlspecialchars($config['estadoPago'] ?? 'completed'); ?>" 
                           placeholder="completed">
                    <small class="form-text text-muted">Estado que se asignará a la orden después de un pago exitoso</small>
                </div>
                
                <hr>
                
                <!-- Debug Logging -->
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="debug_enabled" name="debug_enabled" <?php echo (isset($config['debug_enabled']) && $config['debug_enabled']) ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="debug_enabled">
                            <strong>Activar Debug Logging</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Activa o desactiva el registro de depuración en <code>debug.log</code>. 
                        Útil para diagnosticar problemas durante el desarrollo. 
                        <strong>Recomendado:</strong> Desactivar en producción para mejorar el rendimiento.
                    </small>
                </div>
                
                <hr>
                
                <!-- Submit Button -->
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save mr-2"></i>
                        Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Information Card -->
    <div class="card rounded shadow mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-info-circle mr-2"></i>
                Información del Plugin
            </h5>
        </div>
        <div class="card-body">
            <p><strong>Payku Webpay</strong> permite procesar pagos con:</p>
            <ul>
                <li>Visa</li>
                <li>Mastercard</li>
                <li>Magna</li>
                <li>American Express</li>
                <li>Diners Club</li>
                <li>Redcompra</li>
            </ul>
            <p class="mb-0"><small class="text-muted">Para obtener tu token público, visita <a href="https://app.payku.cl" target="_blank">app.payku.cl</a></small></p>
        </div>
    </div>
</div>

<script>
// Handle form submission
document.getElementById('paykuConfigForm').addEventListener('submit', function(e) {
    // Form will submit normally, controller handles the response
});
</script>

