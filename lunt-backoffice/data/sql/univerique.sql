create or replace table unt_db.univerique
(
    id       int auto_increment
        primary key,
    label    varchar(255) not null,
    cree_le  datetime     not null comment '(DC2Type:datetime_immutable)',
    edite_le datetime     null,
    name     varchar(255) not null
)
    collate = utf8mb4_unicode_ci;


create or replace table unt_db.indexing_config
(
    id            int auto_increment
        primary key,
    full_mode     tinyint(1)   not null,
    index_type    tinyint(1)   not null,
    schedule_at   datetime     not null,
    batch_size    int          not null,
    index_core_id int          null,
    frequency     varchar(255) not null,
    base_uri      varchar(255) null,
    constraint FK_4D2B580B2D9C040
        foreign key (index_core_id) references unt_db.univerique (id)
)
    collate = utf8mb4_unicode_ci;

create or replace index IDX_4D2B580B2D9C040
    on unt_db.indexing_config (index_core_id);

insert into unt_db.univerique (id, label, cree_le, edite_le, name)
values  (1, 'l''UNT 1', '2024-02-15 10:31:43', null, 'unt1'),
        (2, 'l''UNT 2', '2024-06-05 13:52:44', null, 'unt2');