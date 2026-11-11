CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- 2. Lookup tables (dropdown values stored as IDs in other tables)
-- ---------------------------------------------------------------------
CREATE TABLE harvest_remark (
    remark_id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    remark_description VARCHAR(50) NOT NULL UNIQUE,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE quality_grade (
    grade_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    grade_name  VARCHAR(50) NOT NULL UNIQUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE planting_status (
    status_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    status_name  VARCHAR(50) NOT NULL UNIQUE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE soil_type (
    soil_type_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    soil_type_name  VARCHAR(50) NOT NULL UNIQUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE irrigation_type (
    irrigation_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    irrigation_name  VARCHAR(50) NOT NULL UNIQUE,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE crop_category (
    category_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category_name  VARCHAR(50) NOT NULL UNIQUE,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE season (
    season_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    season_name  VARCHAR(50) NOT NULL UNIQUE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------
-- 3. Main tables
-- ---------------------------------------------------------------------
CREATE TABLE farmer (
    farmer_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    first_name       VARCHAR(50)  NOT NULL,
    last_name        VARCHAR(50)  NOT NULL,
    contact_number   VARCHAR(20)  UNIQUE,
    address          VARCHAR(255),
    date_registered  DATE NOT NULL DEFAULT CURRENT_DATE,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE farm (
    farm_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    farmer_id      INT NOT NULL REFERENCES farmer(farmer_id) ON DELETE RESTRICT,
    soil_type_id   INT REFERENCES soil_type(soil_type_id) ON DELETE RESTRICT,
    irrigation_id  INT REFERENCES irrigation_type(irrigation_id) ON DELETE RESTRICT,
    farm_name      VARCHAR(100) NOT NULL,
    location       VARCHAR(255) NOT NULL,
    area_hectares  NUMERIC(10,2) CHECK (area_hectares > 0),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (farmer_id, farm_name)
);

CREATE TABLE crop (
    crop_id              INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category_id          INT REFERENCES crop_category(category_id) ON DELETE RESTRICT,
    season_id            INT REFERENCES season(season_id) ON DELETE RESTRICT,
    crop_name            VARCHAR(100) NOT NULL,
    variety              VARCHAR(100),
    growing_period_days  INT CHECK (growing_period_days > 0),
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (crop_name, variety)
);

CREATE TABLE planting (
    planting_id            INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    farm_id                INT NOT NULL REFERENCES farm(farm_id) ON DELETE RESTRICT,
    crop_id                INT NOT NULL REFERENCES crop(crop_id) ON DELETE RESTRICT,
    status_id              INT NOT NULL DEFAULT 1 REFERENCES planting_status(status_id) ON DELETE RESTRICT,
    planting_date          DATE NOT NULL,
    area_planted           NUMERIC(10,2) CHECK (area_planted > 0),
    seed_quantity          NUMERIC(10,2) CHECK (seed_quantity >= 0),
    expected_harvest_date  DATE,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_expected_harvest_date
        CHECK (expected_harvest_date IS NULL OR expected_harvest_date >= planting_date)
);

CREATE TABLE harvest (
    harvest_id            INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    planting_id           INT NOT NULL REFERENCES planting(planting_id) ON DELETE RESTRICT,
    grade_id              INT REFERENCES quality_grade(grade_id) ON DELETE RESTRICT,
    remark_id             INT NOT NULL REFERENCES harvest_remark(remark_id) ON DELETE RESTRICT,
    harvest_date          DATE NOT NULL,
    quantity_kg           NUMERIC(10,2) NOT NULL CHECK (quantity_kg > 0),
    selling_price_per_kg  NUMERIC(10,2) CHECK (selling_price_per_kg >= 0),
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
-- Note: "harvest_date must not be before planting_date" compares two
-- tables, so it is validated in the PHP app, not with a CHECK.

-- ---------------------------------------------------------------------
-- 4. Indexes on foreign keys (faster joins, filters and reports)
-- ---------------------------------------------------------------------
CREATE INDEX idx_farm_farmer      ON farm(farmer_id);
CREATE INDEX idx_planting_farm    ON planting(farm_id);
CREATE INDEX idx_planting_crop    ON planting(crop_id);
CREATE INDEX idx_planting_status  ON planting(status_id);
CREATE INDEX idx_harvest_planting ON harvest(planting_id);
CREATE INDEX idx_harvest_date     ON harvest(harvest_date);

-- ---------------------------------------------------------------------
-- 5. Triggers: keep updated_at current on all tables
-- ---------------------------------------------------------------------
DO $$
DECLARE
    t TEXT;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'harvest_remark', 'quality_grade', 'planting_status', 'soil_type',
        'irrigation_type', 'crop_category', 'season',
        'farmer', 'farm', 'crop', 'planting', 'harvest'
    ]
    LOOP
        EXECUTE format(
            'CREATE TRIGGER trg_%I_updated_at BEFORE UPDATE ON %I
             FOR EACH ROW EXECUTE FUNCTION set_updated_at()', t, t);
    END LOOP;
END $$;

-- ---------------------------------------------------------------------
-- 6. Security: block the public Supabase API, since the app connects
--    directly to Postgres with the server-side credentials in .env.
-- ---------------------------------------------------------------------
ALTER TABLE harvest_remark  ENABLE ROW LEVEL SECURITY;
ALTER TABLE quality_grade   ENABLE ROW LEVEL SECURITY;
ALTER TABLE planting_status ENABLE ROW LEVEL SECURITY;
ALTER TABLE soil_type       ENABLE ROW LEVEL SECURITY;
ALTER TABLE irrigation_type ENABLE ROW LEVEL SECURITY;
ALTER TABLE crop_category   ENABLE ROW LEVEL SECURITY;
ALTER TABLE season          ENABLE ROW LEVEL SECURITY;
ALTER TABLE farmer          ENABLE ROW LEVEL SECURITY;
ALTER TABLE farm            ENABLE ROW LEVEL SECURITY;
ALTER TABLE crop            ENABLE ROW LEVEL SECURITY;
ALTER TABLE planting        ENABLE ROW LEVEL SECURITY;
ALTER TABLE harvest         ENABLE ROW LEVEL SECURITY;

-- ---------------------------------------------------------------------
-- 7. Lookup data (inserted in order, so the first harvest remark is 1)
-- ---------------------------------------------------------------------
INSERT INTO harvest_remark (remark_description) VALUES
    ('Good Harvest'),
    ('Acceptable Quality'),
    ('Low Yield'),
    ('Poor Quality'),
    ('Damaged Harvest');

INSERT INTO quality_grade (grade_name) VALUES
    ('Grade A'), ('Grade B'), ('Grade C'), ('Reject');

INSERT INTO planting_status (status_name) VALUES
    ('Planted'), ('Growing'), ('Ready for Harvest'), ('Harvested'), ('Failed');

INSERT INTO soil_type (soil_type_name) VALUES
    ('Clay'), ('Clay Loam'), ('Loam'), ('Sandy'), ('Sandy Loam'), ('Silty');

INSERT INTO irrigation_type (irrigation_name) VALUES
    ('Rainfed'), ('Canal / Gravity'), ('Drip'), ('Sprinkler'), ('Flood');

INSERT INTO crop_category (category_name) VALUES
    ('Cereal'), ('Vegetable'), ('Fruit'), ('Root Crop'), ('Legume'), ('Industrial Crop');

INSERT INTO season (season_name) VALUES
    ('Dry Season'), ('Wet Season'), ('Year-round');