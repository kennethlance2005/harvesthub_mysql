CREATE TABLE IF NOT EXISTS CROP_CATALOG (
    CropID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(60) NOT NULL,
    ScientificName VARCHAR(120) NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crop_catalog_name (Name)
) ENGINE=InnoDB;

UPDATE CROP_CATALOG
SET Name = CASE Name
    WHEN 'Dalandan' THEN 'Bitter Orange'
    WHEN 'Duhat' THEN 'Java Plum'
    WHEN 'Ilang-ilang' THEN 'Ylang-Ylang'
    WHEN 'Kamansi' THEN 'Breadnut'
    WHEN 'Kamias' THEN 'Bilimbi'
    WHEN 'Katmon' THEN 'Elephant Apple'
    WHEN 'Mabolo' THEN 'Velvet Apple'
    WHEN 'Nipa' THEN 'Nipa Palm'
    WHEN 'Niyog (Coconut)' THEN 'Coconut'
    WHEN 'Paho' THEN 'Horse Mango'
    WHEN 'Pili' THEN 'Pili Nut'
    WHEN 'Santol' THEN 'Cotton Fruit'
    WHEN 'Sapinit' THEN 'Wild Raspberry'
    WHEN 'Suha (Pummelo)' THEN 'Pummelo'
    WHEN 'Alugbati' THEN 'Malabar Spinach'
    WHEN 'Ampalaya' THEN 'Bitter Melon'
    WHEN 'Bataw' THEN 'Hyacinth Bean'
    WHEN 'Gabi (Taro)' THEN 'Taro'
    WHEN 'Kamote' THEN 'Sweet Potato'
    WHEN 'Kangkong' THEN 'Water Spinach'
    WHEN 'Kundol' THEN 'Winter Melon'
    WHEN 'Langka' THEN 'Jackfruit'
    WHEN 'Luya' THEN 'Ginger'
    WHEN 'Luya (Ginger)' THEN 'Ginger'
    WHEN 'Malunggay' THEN 'Moringa'
    WHEN 'Mango (Carabao)' THEN 'Carabao Mango'
    WHEN 'Mustasa' THEN 'Mustard Greens'
    WHEN 'Pako' THEN 'Fiddlehead Fern'
    WHEN 'Pandang Mabango' THEN 'Fragrant Pandan'
    WHEN 'Patani' THEN 'Lima Bean'
    WHEN 'Patola' THEN 'Ridge Gourd'
    WHEN 'Saluyot' THEN 'Jute Mallow'
    WHEN 'Sigarilyas' THEN 'Winged Bean'
    WHEN 'Siling Labuyo' THEN 'Bird''s Eye Chili'
    WHEN 'Singkamas' THEN 'Jicama'
    WHEN 'Sitaw' THEN 'Yardlong Bean'
    WHEN 'Talong' THEN 'Eggplant'
    WHEN 'Tugi' THEN 'Lesser Yam'
    WHEN 'Ube' THEN 'Purple Yam'
    WHEN 'Upo' THEN 'Bottle Gourd'
    WHEN 'Baguio Beans' THEN 'Green Beans'
    WHEN 'Cabbage (Repolyo)' THEN 'Cabbage'
    WHEN 'Corn (Mais)' THEN 'Corn'
    WHEN 'Cucumber (Pipino)' THEN 'Cucumber'
    WHEN 'Garlic (Bawang)' THEN 'Garlic'
    WHEN 'Onion (Sibuyas)' THEN 'Onion'
    WHEN 'Peanut (Mani)' THEN 'Peanut'
    WHEN 'Pumpkin / Squash (Kalabasa)' THEN 'Squash'
    WHEN 'Radish (Labanos)' THEN 'Radish'
    WHEN 'Tomato (Kamatis)' THEN 'Tomato'
    WHEN 'Guava (Bayabas)' THEN 'Guava'
    WHEN 'Pineapple (Pinya)' THEN 'Pineapple'
    WHEN 'Watermelon (Pakwan)' THEN 'Watermelon'
    WHEN 'Lemongrass (Tanglad)' THEN 'Lemongrass'
    ELSE Name
END
WHERE Name IN (
    'Dalandan', 'Duhat', 'Ilang-ilang', 'Kamansi', 'Kamias', 'Katmon',
    'Mabolo', 'Nipa', 'Niyog (Coconut)', 'Paho', 'Pili', 'Santol', 'Sapinit',
    'Suha (Pummelo)', 'Alugbati', 'Ampalaya', 'Bataw', 'Gabi (Taro)',
    'Kamote', 'Kangkong', 'Kundol', 'Langka', 'Luya', 'Luya (Ginger)',
    'Malunggay', 'Mango (Carabao)', 'Mustasa', 'Pako', 'Pandang Mabango',
    'Patani', 'Patola', 'Saluyot', 'Sigarilyas', 'Siling Labuyo',
    'Singkamas', 'Sitaw', 'Talong', 'Tugi', 'Ube', 'Upo', 'Baguio Beans',
    'Cabbage (Repolyo)', 'Corn (Mais)', 'Cucumber (Pipino)', 'Garlic (Bawang)',
    'Onion (Sibuyas)', 'Peanut (Mani)', 'Pumpkin / Squash (Kalabasa)',
    'Radish (Labanos)', 'Tomato (Kamatis)', 'Guava (Bayabas)',
    'Pineapple (Pinya)', 'Watermelon (Pakwan)', 'Lemongrass (Tanglad)'
);

INSERT INTO CROP_CATALOG (Name, ScientificName) VALUES
    -- Endemic & Native Fruits / Trees
    ('Abaca', 'Musa textilis'),
    ('Alupag', 'Dimocarpus longan subsp. malesianus'),
    ('Batuan', 'Garcinia binucao'),
    ('Bignay', 'Antidesma bunius'),
    ('Calamansi', 'Citrus × microcarpa'),
    ('Bitter Orange', 'Citrus aurantium'),
    ('Java Plum', 'Syzygium cumini'),
    ('Galo', 'Anacolosa frutescens'),
    ('Ylang-Ylang', 'Cananga odorata'),
    ('Kalumpit', 'Terminalia microcarpa'),
    ('Breadnut', 'Artocarpus camansi'),
    ('Bilimbi', 'Averrhoa bilimbi'),
    ('Elephant Apple', 'Dillenia philippinensis'),
    ('Lanzones', 'Lansium parasiticum'),
    ('Lipote', 'Syzygium polycephaloides'),
    ('Lubeg', 'Syzygium lineatum'),
    ('Velvet Apple', 'Diospyros blancoi'),
    ('Marang', 'Artocarpus odoratissimus'),
    ('Nipa Palm', 'Nypa fruticans'),
    ('Coconut', 'Cocos nucifera'),
    ('Horse Mango', 'Mangifera altissima'),
    ('Pili Nut', 'Canarium ovatum'),
    ('Rambutan', 'Nephelium lappaceum'),
    ('Saba Banana', 'Musa acuminata × balbisiana'),
    ('Cotton Fruit', 'Sandoricum koetjape'),
    ('Wild Raspberry', 'Rubus rosifolius'),
    ('Pummelo', 'Citrus maxima'),
    ('Tabon-tabon', 'Atuna racemosa'),
    -- Native & Heritage Vegetables / Root Crops
    ('Malabar Spinach', 'Basella alba'),
    ('Bitter Melon', 'Momordica charantia'),
    ('Hyacinth Bean', 'Lablab purpureus'),
    ('Taro', 'Colocasia esculenta'),
    ('Sweet Potato', 'Ipomoea batatas'),
    ('Water Spinach', 'Ipomoea aquatica'),
    ('Winter Melon', 'Benincasa hispida'),
    ('Jackfruit', 'Artocarpus heterophyllus'),
    ('Ginger', 'Zingiber officinale'),
    ('Moringa', 'Moringa oleifera'),
    ('Carabao Mango', 'Mangifera indica'),
    ('Mustard Greens', 'Brassica juncea'),
    ('Fiddlehead Fern', 'Diplazium esculentum'),
    ('Fragrant Pandan', 'Pandanus amaryllifolius'),
    ('Lima Bean', 'Phaseolus lunatus'),
    ('Ridge Gourd', 'Luffa acutangula'),
    ('Jute Mallow', 'Corchorus olitorius'),
    ('Winged Bean', 'Psophocarpus tetragonolobus'),
    ('Bird''s Eye Chili', 'Capsicum frutescens'),
    ('Jicama', 'Pachyrhizus erosus'),
    ('Yardlong Bean', 'Vigna unguiculata subsp. sesquipedalis'),
    ('Eggplant', 'Solanum melongena'),
    ('Lesser Yam', 'Dioscorea esculenta'),
    ('Purple Yam', 'Dioscorea alata'),
    ('Bottle Gourd', 'Lagenaria siceraria'),
    -- Common & Global Vegetables, Root Crops & Legumes
    ('Green Beans', 'Phaseolus vulgaris'),
    ('Bell Pepper', 'Capsicum annuum'),
    ('Broccoli', 'Brassica oleracea var. italica'),
    ('Cabbage', 'Brassica oleracea var. capitata'),
    ('Carrot', 'Daucus carota subsp. sativus'),
    ('Cauliflower', 'Brassica oleracea var. botrytis'),
    ('Celery', 'Apium graveolens'),
    ('Corn', 'Zea mays'),
    ('Cucumber', 'Cucumis sativus'),
    ('Garlic', 'Allium sativum'),
    ('Lettuce', 'Lactuca sativa'),
    ('Onion', 'Allium cepa'),
    ('Peanut', 'Arachis hypogaea'),
    ('Peas', 'Pisum sativum'),
    ('Potato', 'Solanum tuberosum'),
    ('Squash', 'Cucurbita maxima'),
    ('Radish', 'Raphanus sativus'),
    ('Spinach', 'Spinacia oleracea'),
    ('Tomato', 'Solanum lycopersicum'),
    -- Common & Global Fruits
    ('Apple', 'Malus domestica'),
    ('Avocado', 'Persea americana'),
    ('Banana (Cavendish)', 'Musa acuminata'),
    ('Dragon Fruit', 'Selenicereus undatus'),
    ('Grapes', 'Vitis vinifera'),
    ('Guava', 'Psidium guajava'),
    ('Lemon', 'Citrus limon'),
    ('Melon', 'Cucumis melo'),
    ('Orange', 'Citrus × sinensis'),
    ('Papaya', 'Carica papaya'),
    ('Pineapple', 'Ananas comosus'),
    ('Strawberry', 'Fragaria × ananassa'),
    ('Watermelon', 'Citrullus lanatus'),
    -- Common Herbs & Spices
    ('Basil', 'Ocimum basilicum'),
    ('Chives', 'Allium schoenoprasum'),
    ('Cilantro / Coriander', 'Coriandrum sativum'),
    ('Lemongrass', 'Cymbopogon citratus'),
    ('Mint', 'Mentha'),
    ('Oregano', 'Origanum vulgare'),
    ('Parsley', 'Petroselinum crispum'),
    ('Rosemary', 'Salvia rosmarinus')
ON DUPLICATE KEY UPDATE ScientificName = VALUES(ScientificName);

CREATE TABLE IF NOT EXISTS CROP_CATALOG_REQUEST (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID INT NOT NULL,
    CropName VARCHAR(60) NOT NULL,
    ScientificName VARCHAR(120) NULL,
    Notes VARCHAR(500) NULL,
    Status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    ReviewReason VARCHAR(1000) NULL,
    ReviewedBy INT NULL,
    RequestedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ReviewedAt DATETIME NULL,
    INDEX idx_crop_catalog_request_status (Status, RequestedAt),
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID) ON DELETE CASCADE,
    FOREIGN KEY (ReviewedBy) REFERENCES GARDEN_COORDINATOR(CoordID) ON DELETE SET NULL
) ENGINE=InnoDB;