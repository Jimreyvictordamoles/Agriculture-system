<?php
// Shared by add.php and edit.php.
// Expects: $values, $errors, $farmers, $soilTypes, $irrigationTypes, $submitLabel
?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" action="">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="farmer_id" class="form-label">Owner (farmer) <span class="text-danger">*</span></label>
            <select class="form-select" id="farmer_id" name="farmer_id" required>
                <option value="">-- Select farmer --</option>
                <?php foreach ($farmers as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['farmer_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="farm_name" class="form-label">Farm name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="farm_name" name="farm_name"
                   value="<?= e($values['farm_name']) ?>" maxlength="100" required>
        </div>

        <div class="col-12">
            <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="location" name="location"
                   value="<?= e($values['location']) ?>" maxlength="255"
                   placeholder="Barangay, city / municipality, province" required>
        </div>

        <div class="col-md-4">
            <label for="area_hectares" class="form-label">Area (hectares) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="area_hectares" name="area_hectares"
                   value="<?= e($values['area_hectares']) ?>" min="0.01" max="100000" step="0.01" required>
        </div>

        <div class="col-md-4">
            <label for="soil_type_id" class="form-label">Soil type <span class="text-danger">*</span></label>
            <select class="form-select" id="soil_type_id" name="soil_type_id" required>
                <option value="">-- Select soil type --</option>
                <?php foreach ($soilTypes as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['soil_type_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="irrigation_id" class="form-label">Irrigation type <span class="text-danger">*</span></label>
            <select class="form-select" id="irrigation_id" name="irrigation_id" required>
                <option value="">-- Select irrigation --</option>
                <?php foreach ($irrigationTypes as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['irrigation_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><?= e($submitLabel) ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
