create or replace table unt_db.univerique
(
    id       int auto_increment
        primary key,
    label    varchar(255) not null,
    cree_le  datetime     not null comment '(DC2Type:datetime_immutable)',
    edite_le datetime     null,
    name     varchar(255) not null
) collate = utf8mb4_unicode_ci;

CREATE TABLE `indexing_config` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
   `full_mode` tinyint(1) NOT NULL,
   `index_type` tinyint(1) NOT NULL,
   `schedule_at` datetime DEFAULT NULL,
   `batch_size` int(11) NOT NULL,
   `index_core_id` int(11) DEFAULT NULL,
   `every_valeur` int(11) NOT NULL,
   `every_unite` varchar(10) NOT NULL,
   PRIMARY KEY (`id`),
   KEY `IDX_4D2B580B2D9C040` (`index_core_id`),
   CONSTRAINT `FK_4D2B580B2D9C040` FOREIGN KEY (`index_core_id`) REFERENCES `univerique` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

create or replace index IDX_4D2B580B2D9C040 on unt_db.indexing_config (index_core_id);

insert into unt_db.univerique (id, label, cree_le, edite_le, name)
values  (1, 'l''UNT 1', '2024-02-15 10:31:43', null, 'unt1'),
        (2, 'l''UNT 2', '2024-06-05 13:52:44', null, 'unt2');