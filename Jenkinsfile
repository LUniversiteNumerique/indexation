pipeline {
  agent any

  environment {
    DOCKERTAG = "$BUILD_NUMBER$DOCKERTAGEND"
  }

  stages {
    stage('SonarQube Analysis') {
      agent {
        docker {
          image 'sonarsource/sonar-scanner-cli:4.6'
          args '-v $PWD:/usr/src'
          reuseNode true
        }
      }
      steps {
        withSonarQubeEnv('SonarQube SSL') {
          script {
            // Execution de l'analyse sonar
            sh 'sonar-scanner -Dsonar.projectKey=$SONARPROJECTKEY -Dsonar.projectVersion=$BUILD_NUMBER -Dproject.settings=sonar-project.properties'
          }
        }
      }
    }

    stage('Docker build image backoffice') {
      steps {
        script {
          // Construction de l'image docker
          sh 'docker build -t lunt-backoffice:latest -t lunt-backoffice:$DOCKERTAG lunt-backoffice'

          // Tag de l'image docker pour le nexus
          sh 'docker tag lunt-backoffice:latest sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker tag lunt-backoffice:$DOCKERTAG sslv-nexus.coexya.eu/lunt-backoffice:$DOCKERTAG'

          // Upload de l'image docker sur le nexus
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:$DOCKERTAG'

          // Suppression des images docker locales
          sh 'docker rmi -f lunt-backoffice:latest'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker rmi -f lunt-backoffice:$DOCKERTAG'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:$DOCKERTAG'
        }
      }
    }
  }
}
