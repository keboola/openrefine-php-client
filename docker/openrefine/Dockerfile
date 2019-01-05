FROM anapsix/alpine-java:8_jdk

RUN apk --update add apache-ant wget git

RUN mkdir /app
WORKDIR /app

RUN wget -q https://github.com/OpenRefine/OpenRefine/archive/2.6-rc.2.tar.gz && \
    tar xzf 2.6-rc.2.tar.gz && \
    rm -f 2.6-rc.2.tar.gz

WORKDIR /app/OpenRefine-2.6-rc.2
RUN ant build jar_server jar_webapp

WORKDIR /app/OpenRefine-2.6-rc.2/extensions
RUN wget  -q https://github.com/fadmaa/grefine-rdf-extension/archive/v0.9.0.tar.gz && \
    tar xzf v0.9.0.tar.gz && \
    mv grefine-rdf-extension-0.9.0 rdf-extension && \
    rm -f v0.9.0.tar.gz

WORKDIR /app/OpenRefine-2.6-rc.2/extensions/rdf-extension
RUN JAVA_TOOL_OPTIONS='-Dfile.encoding=UTF-8' ant build

WORKDIR /usr/share/

RUN find /usr/share/ca-certificates/mozilla/ -name *.crt -exec keytool -import -trustcacerts \
        -keystore /usr/lib/jvm/java-1.8-openjdk/jre/lib/security/cacerts -storepass changeit -noprompt \
        -file {} -alias {} \; && \
        keytool -list -keystore /usr/lib/jvm/java-1.8-openjdk/jre/lib/security/cacerts --storepass changeit

