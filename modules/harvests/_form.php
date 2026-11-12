<?php
// Shared by add.php and edit.php.
// Expects: $values, $errors, $plantings, $grades, $remarks, $submitLabel
// Optional: $showMarkHarvested (true on the add page only), $markHarvested
?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" action="">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-12">
            <label for="planting_id" class="form-label">Planting <span class="text-danger">*</span></label>
            <select class="form-select" id="planting_id" name="planting_id" required>
                <option value="">-- Select planting --</option>
                <?php foreach ($plantings as $id => $planting): ?>
                    <option value="<?= $id ?>" <?= (string) $values['planting_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($planting['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="harvest_date" class="form-label">Harvest date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="harvest_date" name="harvest_date"
                   value="<?= e($values['harvest_date']) ?>" max="<?= e(today_manila()) ?>" required>
        </div>

        <div class="col-md-4">
            <label for="quantity_kg" class="form-label">Quantity (kg) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="quantity_kg" name="quantity_kg"
                   value="<?= e($values['quantity_kg']) ?>" min="0.01" max="1000000" step="0.01" required>
        </div>

        <div class="col-md-4">
            <label for="selling_price_per_kg" class="form-label">Selling price per kg <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text">&#8369;</span>
                <input type="number" class="form-control" id="selling_price_per_kg" name="selling_price_per_kg"
                       value="<?= e($values['selling_price_per_kg']) ?>" min="0" max="100000" step="0.01" required>
            </div>
        </div>

        <div class="col-md-6">
            <label for="grade_id" class="form-label">Quality grade <span class="text-danger">*</span></label>
            <select class="form-select" id="grade_id" name="grade_id" required>
                <option value="">-- Select grade --</option>
                <?php foreach ($grades as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['grade_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="remark_id" class="form-label">Remark <span class="text-danger">*</span></label>
            <select class="form-select" id="remark_id" name="remark_id" required>
                <option value="">-- Select remark --</option>
                <?php foreach ($remarks as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['remark_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($showMarkHarvested)): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="mark_harvested" name="mark_harvested" value="1"
                           <?= !empty($markHarvested) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mark_harvested">
                        This was the final harvest: mark the planting as <strong>Harvested</strong>
                    </label>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><?= e($submitLabel) ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
