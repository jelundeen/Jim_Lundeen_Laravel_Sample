CREATE
    OR REPLACE VIEW "public"."merged_daily_events_ui_view" AS
SELECT
    DISTINCT 'Live':: character varying AS content_type,
             date_trunc(
                     'day':: character varying:: text,
                     convert_timezone(
                             'EST5EDT':: character varying:: text,
                             '1970-01-01 00:00:00':: timestamp without time zone + (a.event_timestamp / 1000):: double precision * '00:00:01':: interval
                         )
                 ) AS eventdate,
             a.event_datekey,
             CASE
                 WHEN a.platform:: text = 'ios':: character varying:: text THEN 'Mobile':: character varying
                 WHEN a.platform:: text = 'chromecast':: character varying:: text THEN 'Connected':: character varying
                 WHEN a.platform:: text = 'firetv':: character varying:: text THEN 'Connected':: character varying
                 WHEN a.platform:: text = 'android':: character varying:: text THEN 'Mobile':: character varying
                 ELSE 'Web':: character varying END AS platform,
             CASE
                 WHEN a.company IS NULL    OR a.company:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.company END AS brand,
             CASE
                 WHEN a.series_name IS NULL
                     OR a.series_name:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.series_name END AS series_title,
             CASE
                 WHEN a.season_number IS NULL
                     OR a.season_number:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.season_number END AS season_number,
             CASE
                 WHEN a.episode_number IS NULL
                     OR a.episode_number:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.episode_number END AS episode_number,
             CASE
                 WHEN a.episode_name IS NULL
                     OR a.episode_name:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.episode_name END AS episode_name,
             CASE
                 WHEN b.tms_episode_id IS NULL
                     OR b.tms_episode_id:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE b.tms_episode_id END AS gracenote_id,
             a.airdate,
             a.event_id,
             a.device_id,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0:: bigint
                 ELSE a.duration_secs:: bigint END AS duration_secs,
             CASE
                 WHEN a.viewed_secs IS NULL
                     OR a.viewed_secs:: text = '0':: character varying:: text THEN 0:: bigint
                 ELSE a.viewed_secs:: bigint END AS viewed_secs,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 25:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct25_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 50:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct50_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 75:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct75_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 95:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct95_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) >= 100:: numeric:: numeric(18, 0) THEN 1
    ELSE 0 END AS pct100_viewed,
    a.x1_user_type
FROM
    sift_distributed_event a
        JOIN sift_live_assets b ON a.program_id:: text = b.program_id:: text
        AND a.entity_type:: text = b.entity_type:: text
        LEFT JOIN (
        SELECT
            DISTINCT live_filtered_devices.device_id,
                     live_filtered_devices.program_id
        FROM
            live_filtered_devices
        WHERE
                live_filtered_devices.time_diff = true
          AND live_filtered_devices.program_count > 3
    ) d ON a.device_id:: text = d.device_id:: text
        AND a.program_id:: text = d.program_id:: text
WHERE
        a.entity_type:: text = 'Live':: character varying:: text
  AND date_diff(
              'day':: character varying:: text,
              date_trunc(
                      'day':: character varying:: text,
                      convert_timezone(
                              'EST5EDT':: character varying:: text,
                              '1970-01-01 00:00:00':: timestamp without time zone + (a.event_timestamp / 1000):: double precision * '00:00:01':: interval
                          )
                  ),
              date_trunc(
                      'day':: character varying:: text,
                      date_add(
                              'day':: character varying:: text,
                              - 1:: bigint,
                              getdate()
                          )
                  )
          ) >= 0
  AND date_diff(
              'day':: character varying:: text,
              date_trunc(
                      'day':: character varying:: text,
                      convert_timezone(
                              'EST5EDT':: character varying:: text,
                              '1970-01-01 00:00:00':: timestamp without time zone + (a.event_timestamp / 1000):: double precision * '00:00:01':: interval
                          )
                  ),
              date_trunc(
                      'day':: character varying:: text,
                      date_add(
                              'day':: character varying:: text,
                              - 1:: bigint,
                              getdate()
                          )
                  )
          ) <= 7
  AND a.event_datekey:: numeric:: numeric(18, 0) >= to_char(
        date_add(
                'day':: character varying:: text,
                - 15:: bigint,
                getdate()
            ),
        'YYYYMMDD':: character varying:: text
    ):: numeric(18, 0)
  AND a.event_datekey:: numeric:: numeric(18, 0) <= to_char(getdate(), 'YYYYMMDD':: character varying:: text):: numeric(18, 0)
  AND d.device_id IS NULL
  AND b.last_update IS NOT NULL
UNION ALL
SELECT
    DISTINCT 'OnDemand':: character varying AS content_type,
             date_trunc(
                     'day':: character varying:: text,
                     convert_timezone(
                             'EST5EDT':: character varying:: text,
                             '1970-01-01 00:00:00':: timestamp without time zone + (a.event_timestamp / 1000):: double precision * '00:00:01':: interval
                         )
                 ) AS eventdate,
             a.event_datekey,
             CASE
                 WHEN a.platform:: text = 'ios':: character varying:: text THEN 'Mobile':: character varying
                 WHEN a.platform:: text = 'chromecast':: character varying:: text THEN 'Connected':: character varying
                 WHEN a.platform:: text = 'firetv':: character varying:: text THEN 'Connected':: character varying
                 WHEN a.platform:: text = 'android':: character varying:: text THEN 'Mobile':: character varying
                 ELSE 'Web':: character varying END AS platform,
             CASE
                 WHEN a."provider" IS NULL
                     OR a."provider":: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a."provider" END AS brand,
             CASE
                 WHEN a.series_name IS NULL
                     OR a.series_name:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.series_name END AS series_title,
             CASE
                 WHEN a.season_number IS NULL
                     OR a.season_number:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.season_number END AS season_number,
             CASE
                 WHEN a.episode_number IS NULL
                     OR a.episode_number:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.episode_number END AS episode_number,
             CASE
                 WHEN a.episode_name IS NULL
                     OR a.episode_name:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE a.episode_name END AS episode_name,
             CASE
                 WHEN b.tms_episode_id IS NULL
                     OR b.tms_episode_id:: text = '':: character varying:: text THEN 'N/A':: character varying
                 ELSE b.tms_episode_id END AS gracenote_id,
             a.airdate,
             a.event_id,
             a.device_id,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 ELSE a.duration_secs:: integer END AS duration_secs,
             CASE
                 WHEN a.viewed_secs IS NULL
                     OR a.viewed_secs:: text = '0':: character varying:: text THEN 0
                 ELSE a.viewed_secs:: integer END AS viewed_secs,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 25:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct25_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 50:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct50_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 75:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct75_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) > 95:: numeric:: numeric(18, 0) THEN 1
                 ELSE 0 END AS pct95_viewed,
             CASE
                 WHEN a.duration_secs IS NULL
                     OR a.viewed_secs IS NULL
                     OR a.duration_secs:: text = '0':: character varying:: text THEN 0
                 WHEN (
                                  100:: numeric:: numeric(18, 0) * a.viewed_secs:: numeric(18, 0) / a.duration_secs:: numeric(18, 0)
                          ) >= 100:: numeric:: numeric(18, 0) THEN 1
    ELSE 0 END AS pct100_viewed,
    a.x1_user_type
FROM
    sift_distributed_event a
        JOIN sift_ondemand_assets b ON a.media_guid:: text = b.media_guid:: text
        AND a.entity_type:: text = b.entity_type:: text
WHERE
        a.entity_type:: text = 'On-Demand':: character varying:: text
  AND date_diff(
              'day':: character varying:: text,
              date_trunc(
                      'day':: character varying:: text,
                      convert_timezone(
                              'EST5EDT':: character varying:: text,
                              '1970-01-01 00:00:00':: timestamp without time zone + (a.event_timestamp / 1000):: double precision * '00:00:01':: interval
                          )
                  ),
              date_trunc(
                      'day':: character varying:: text,
                      date_add(
                              'day':: character varying:: text,
                              - 1:: bigint,
                              getdate()
                          )
                  )
          ) >= 0
  AND date_diff(
              'day':: character varying:: text,
              date_trunc(
                      'day':: character varying:: text,
                      convert_timezone(
                              'EST5EDT':: character varying:: text,
                              '1970-01-01 00:00:00':: timestamp without time zone + (a.event_timestamp / 1000):: double precision * '00:00:01':: interval
                          )
                  ),
              date_trunc(
                      'day':: character varying:: text,
                      date_add(
                              'day':: character varying:: text,
                              - 1:: bigint,
                              getdate()
                          )
                  )
          ) <= 7
  AND a.event_datekey:: numeric:: numeric(18, 0) >= to_char(
        date_add(
                'day':: character varying:: text,
                - 15:: bigint,
                getdate()
            ),
        'YYYYMMDD':: character varying:: text
    ):: numeric(18, 0)
  AND a.event_datekey:: numeric:: numeric(18, 0) <= to_char(getdate(), 'YYYYMMDD':: character varying:: text):: numeric(18, 0)
  AND b.last_update IS NOT NULL;