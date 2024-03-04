pipeline {
  agent any

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
      when {
        buildingTag()
        beforeAgent true
      }
      steps {
        script {
          // Construction de l'image docker
          sh 'docker build -t lunt-backoffice:latest -t lunt-backoffice:$TAG_NAME lunt-backoffice'

          // Tag de l'image docker pour le nexus
          sh 'docker tag lunt-backoffice:latest sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker tag lunt-backoffice:$TAG_NAME sslv-nexus.coexya.eu/lunt-backoffice:$TAG_NAME'

          // Upload de l'image docker sur le nexus
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:$TAG_NAME'

          // Suppression des images docker locales
          sh 'docker rmi -f lunt-backoffice:latest'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker rmi -f lunt-backoffice:$TAG_NAME'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:$TAG_NAME'
        }
      }
    }

    stage('Update development platform') {
      when {
        buildingTag()
        beforeAgent true
      }
      steps {
        sshagent(['jenkins-lunt-ssh-development-user-pwd']) {
          sh("ssh -tt user@sslv-lunt-develop.lyon-dev2.local VERSION=$DOCKERTAG docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml up -d")
        }
      }
    }
  }
}
