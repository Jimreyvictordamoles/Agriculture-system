<?php
// Shared by add.php and edit.php.
// Expects: $values, $errors, $farms, $crops, $statuses, $submitLabel
?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" action="">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="farm_id" class="form-label">Farm <span class="text-danger">*</span></label>
            <select class="form-select" id="farm_id" name="farm_id" required>
                <option value="">-- Select farm --</option>
                <?php foreach ($farms as $id => $farm): ?>
                    <option value="<?= $id ?>" <?= (string) $values['farm_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($farm['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="crop_id" class="form-label">Crop <span class="text-danger">*</span></label>
            <select class="form-select" id="crop_id" name="crop_id" required>
                <option value="">-- Select crop --</option>
                <?php foreach ($crops as $id => $crop): ?>
                    <option value="<?= $id ?>" data-days="<?= $crop['days'] === null ? '' : (int) $crop['days'] ?>"
                        <?= (string) $values['crop_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($crop['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="planting_date" class="form-label">Planting date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="planting_date" name="planting_date"
                   value="<?= e($values['planting_date']) ?>" max="<?= e(today_manila()) ?>" required>
        </div>

        <div class="col-md-4">
            <label for="expected_harvest_date" class="form-label">Expected harvest date</label>
            <input type="date" class="form-control" id="expected_harvest_date" name="expected_harvest_date"
                   value="<?= e($values['expected_harvest_date']) ?>">
            <div class="form-text">Filled in for you from the crop's growing period. You can change it.</div>
        </div>

        <div class="col-md-4">
            <label for="status_id" class="form-label">Status <span class="text-danger">*</span></label>
            <select class="form-select" id="status_id" name="status_id" required>
                <option value="">-- Select status --</option>
                <?php foreach ($statuses as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['status_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="area_planted" class="form-label">Area planted (hectares) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="area_planted" name="area_planted"
                   value="<?= e($values['area_planted']) ?>" min="0.01" max="100000" step="0.01" required>
        </div>

        <div class="col-md-6">
            <label for="seed_quantity" class="form-label">Seed quantity (kg) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="seed_quantity" name="seed_quantity"
                   value="<?= e($values['seed_quantity']) ?>" min="0" max="1000000" step="0.01" required>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><?= e($submitLabel) ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
// Suggest the expected harvest date: planting date + the crop's growing period.
// It only fills the box while you have not typed your own date there.
(function () {
    var crop     = document.getElementById('crop_id');
    var planted  = document.getElementById('planting_date');
    var expected = document.getElementById('expected_harvest_date');
    var auto     = expected.value === '';

    expected.addEventListener('input', function () { auto = false; });

    function suggest() {
        if (!auto) { return; }
        var option = crop.options[crop.selectedIndex];
        var days   = option ? parseInt(option.getAttribute('data-days'), 10) : NaN;
        if (!planted.value || isNaN(days)) { return; }

        var date = new Date(planted.value + 'T00:00:00');
        date.setDate(date.getDate() + days);
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day   = String(date.getDate()).padStart(2, '0');
        expected.value = date.getFullYear() + '-' + month + '-' + day;
    }

    crop.addEventListener('change', suggest);
    planted.addEventListener('change', suggest);
})();
</script>
