CREATE TABLE `xpto`
(
    `id`         INT PRIMARY KEY NOT NULL COMMENT 'Unique identifier.',
    `created_at` TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Date when the record was inserted.'
);
