pipeline {
  agent any
  post {
    failure {
      updateGitlabCommitStatus name: 'build', state: 'failed'
    }
    success {
      updateGitlabCommitStatus name: 'build', state: 'success'
    }
    aborted {
      updateGitlabCommitStatus name: 'build', state: 'canceled'
    }
  }
  options {
    gitLabConnection('SSL Gitlab')
  }

  environment {
    def tagName = tagName()
    def SONARPROJECTKEY = 'lunt-indexation-notice-develop'
  }

  triggers {
    gitlab(
      triggerOnPush: true,
      triggerOnMergeRequest: false
    )
  }

  stages {

    stage('Build Details') {
      steps {
        script {
          sh 'echo "GitLab Source Branch: ${gitlabSourceBranch}"'
          sh 'echo "GitLab Action Type: ${gitlabActionType}"'
          sh 'echo "Calculated Tag Name: ${tagName}"'
        }
      }
    }

    stage('SonarQube Analysis') {
      when {
        environment name: 'gitlabSourceBranch', value: 'develop'
        beforeAgent true
      }
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
        environment name: 'gitlabActionType', value: 'TAG_PUSH'
        beforeAgent true
      }
      steps {
        script {
          // Construction de l'image docker
          sh 'docker build -t lunt-backoffice:latest -t lunt-backoffice:$tagName lunt-backoffice'

          // Tag de l'image docker pour le nexus
          sh 'docker tag lunt-backoffice:latest sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker tag lunt-backoffice:$tagName sslv-nexus.coexya.eu/lunt-backoffice:$tagName'

          // Upload de l'image docker sur le nexus
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:$tagName'

          // Suppression des images docker locales
          sh 'docker rmi -f lunt-backoffice:latest'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker rmi -f lunt-backoffice:$tagName'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:$tagName'
        }
      }
    }

    stage('Update development platform') {
      agent {
        docker {
          image 'ictu/sshpass'
          reuseNode true
        }
      }
      when {
        environment name: 'gitlabActionType', value: 'TAG_PUSH'
        beforeAgent true
      }
      steps {
        withCredentials([string(credentialsId: 'jenkins-lunt-ssh-development-user-pwd', variable: 'VAR')]) {
          script {
            // Mise à jour de l'environnement avec la nouvelle version
            sh 'sshpass -p ${VAR} ssh -oStrictHostKeyChecking=no user@sslv-lunt-develop.lyon-dev2.local VERSION=${tagName} docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml up -d'

            // Réinitialisation des données
            sh 'sshpass -p ${VAR} ssh -oStrictHostKeyChecking=no user@sslv-lunt-develop.lyon-dev2.local sh /home/user/lunt-indexation-notice/reset-backoffice-db.sh'
          }
        }
      }
    }
  }
}

def tagName() {
    return "${gitlabSourceBranch}" ? "${gitlabSourceBranch}".split('/')[-1] : 'snapshot'
}
