FROM frekele/ant:1.10-jdk8 as builder

WORKDIR /usr/local
# analyser cette partie pour faire autrement, cette url change à chaque nouvelle version de tomcat
RUN curl --location 'https://archive.apache.org/dist/tomcat/tomcat-9/v9.0.52/bin/apache-tomcat-9.0.52.tar.gz' | tar xz
RUN cd apache-tomcat* && echo "catalina.home=$(pwd)" > ~/build.properties

COPY . /tmp/joai-project
WORKDIR /tmp/joai-project
RUN ant dist
RUN mkdir /war
WORKDIR /war
RUN unzip /tmp/joai-project/dist/oai.war


# stage 2
FROM tomcat:9-jre8-alpine
LABEL author="Tom Saleeba"

RUN apk add tzdata

ENV TZ Europe/Paris

WORKDIR /usr/local/tomcat/webapps
COPY --from=builder /war/ ./joai/
COPY --from=builder /tmp/joai-project/server.xml /usr/local/tomcat/conf/server.xml
RUN \
  wget -O ../lib/woodstox-core-5.0.3.jar 'https://search.maven.org/remotecontent?filepath=com/fasterxml/woodstox/woodstox-core/5.0.3/woodstox-core-5.0.3.jar' && \
  wget -O ../lib/stax2-api-4.0.0.jar 'https://search.maven.org/remotecontent?filepath=org/codehaus/woodstox/stax2-api/4.0.0/stax2-api-4.0.0.jar' && \
  wget -O ../lib/xalan-2.7.2.jar 'https://repo1.maven.org/maven2/xalan/xalan/2.7.2/xalan-2.7.2.jar' && \
   \
  rm -r docs/ examples/ host-manager/ manager/ && \
  mkdir -p /joai/config/harvester /joai/config/repository && \
  ln -s /joai/config/harvester /usr/local/tomcat/webapps/joai/WEB-INF/harvester_settings_and_data && \
  ln -s /joai/config/repository /usr/local/tomcat/webapps/joai/WEB-INF/repository_settings_and_data

# just the config
VOLUME /joai/config
# the harvested/provided records
VOLUME /joai/data
