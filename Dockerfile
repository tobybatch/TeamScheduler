FROM tobybatch/teamscheduler:latest

ADD . /opt/drupal/web/modules/custom/team_scheduler

ENTRYPOINT vendor/bin/drush rs 0.0.0.0:8888

# docker build -t tobybatch/teamscheduler:instance .
# docker run -ti -p 8888:8888 tobybatch/teamscheduler:instance
